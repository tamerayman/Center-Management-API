<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseFee;
use App\Models\CourseSubscription;
use App\Models\FinancialAuditLog;
use App\Models\MonthlyCharge;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\StudentCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class FinancialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretary;

    private User $teacher;

    private User $otherTeacher;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->secretary = User::factory()->secretary()->create();
        $this->teacher = User::factory()->teacher()->create();
        $this->otherTeacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();

        $this->course = Course::factory()->create(['teacher_id' => $this->teacher->id]);

        // سعر الكورس: 600 EGP من أول الشهر الماضي
        CourseFee::create([
            'course_id' => $this->course->id,
            'monthly_fee' => 600,
            'effective_from' => now()->subMonth()->toDateString(),
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * تسجيل الطالب في الكورس + فتح اشتراك (مثل join).
     */
    private function enroll(?User $actor = null): CourseSubscription
    {
        $this->actingAs($actor ?? $this->student)
            ->postJson("/api/courses/{$this->course->id}/join")
            ->assertOk();

        return $this->course->subscriptions()->where('student_id', $this->student->id)->first();
    }

    private function generateCharges(User $actor, ?string $period = null): void
    {
        [$year, $month] = $this->period($period);

        $this->actingAs($actor)->postJson('/api/finance/charges/generate', [
            'period_year' => $year,
            'period_month' => $month,
        ])->assertOk();
    }

    private function recordPayment(User $actor, array $overrides = []): TestResponse
    {
        [$year, $month] = $this->period($overrides['period'] ?? null);
        unset($overrides['period']);

        return $this->actingAs($actor)->postJson('/api/finance/payments', array_merge([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'period_year' => $year,
            'period_month' => $month,
            'amount' => 300,
            'method' => 'cash',
        ], $overrides));
    }

    private function period(?string $period = null): array
    {
        if ($period) {
            [$y, $m] = explode('-', $period);

            return [(int) $y, (int) $m];
        }

        return [now()->year, now()->month];
    }

    private function lastMonth(): string
    {
        return now()->subMonth()->format('Y-n');
    }

    // ---------------------------------------------------------------
    // الصلاحيات (سيناريوهات 1-5، 19، 22، 23)
    // ---------------------------------------------------------------

    public function test_admin_can_record_payment(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 600])->assertCreated();

        $this->assertDatabaseHas('payments', [
            'student_id' => $this->student->id,
            'amount' => 600,
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_secretary_can_record_payment(): void
    {
        $this->enroll();
        $this->recordPayment($this->secretary, ['amount' => 300])->assertCreated();

        $this->assertDatabaseHas('payments', ['created_by' => $this->secretary->id]);
    }

    public function test_teacher_can_record_payment_for_his_own_course(): void
    {
        $this->enroll();
        $this->recordPayment($this->teacher, ['amount' => 300])->assertCreated();
    }

    public function test_teacher_cannot_record_payment_for_another_teachers_course(): void
    {
        $this->enroll();

        $this->recordPayment($this->otherTeacher, ['amount' => 300])
            ->assertForbidden();
    }

    public function test_student_cannot_record_payment(): void
    {
        $this->enroll();

        $this->recordPayment($this->student, ['amount' => 300])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_financial_endpoints(): void
    {
        $this->getJson('/api/finance/payments')->assertUnauthorized();
        $this->getJson('/api/finance/dashboard')->assertUnauthorized();
        $this->getJson('/api/finance/overdue')->assertUnauthorized();
    }

    public function test_teacher_cannot_access_global_financial_views(): void
    {
        $this->actingAs($this->teacher)->getJson('/api/finance/dashboard')->assertForbidden();
        $this->actingAs($this->teacher)->getJson('/api/finance/overdue')->assertForbidden();
        $this->actingAs($this->teacher)->getJson('/api/finance/reports/monthly')->assertForbidden();

        // لكن إحصائيات كورسه مسموحة، وكورس غيره ممنوعة
        $this->actingAs($this->teacher)->getJson("/api/finance/courses/{$this->course->id}")->assertOk();
        $otherCourse = Course::factory()->create(['teacher_id' => $this->otherTeacher->id]);
        $this->actingAs($this->teacher)->getJson("/api/finance/courses/{$otherCourse->id}")->assertForbidden();
    }

    public function test_secretary_cannot_perform_sensitive_operations(): void
    {
        $this->enroll();

        // توليد فواتير + إنشاء اشتراك + تعديل سعر = أدمن فقط
        $this->actingAs($this->secretary)->postJson('/api/finance/charges/generate', [
            'period_year' => now()->year,
            'period_month' => now()->month,
        ])->assertForbidden();

        $this->actingAs($this->secretary)->postJson('/api/finance/subscriptions', [
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
        ])->assertForbidden();

        $this->actingAs($this->secretary)->postJson("/api/finance/courses/{$this->course->id}/fees", [
            'monthly_fee' => 700,
        ])->assertForbidden();
    }

    // ---------------------------------------------------------------
    // partial / full / overpayment / credit (سيناريوهات 6-9)
    // ---------------------------------------------------------------

    public function test_partial_payment_creates_partially_paid_charge(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 200])->assertCreated();

        $charge = MonthlyCharge::first();
        // السعر 600 (بدون خصم) → الفاتورة 600، المدفوع 200
        $this->assertEquals('600.00', $charge->amount);
        $this->assertEquals('200.00', $charge->paid_amount);
        $this->assertEquals('partially_paid', $charge->status);
    }

    public function test_full_payment_sets_charge_to_paid(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 600])->assertCreated();

        $charge = MonthlyCharge::first();
        $this->assertEquals('600.00', $charge->paid_amount);
        $this->assertEquals('paid', $charge->status);
    }

    public function test_two_partial_payments_complete_the_month(): void
    {
        $this->enroll();

        $this->recordPayment($this->admin, ['amount' => 200])->assertCreated();
        $this->recordPayment($this->admin, ['amount' => 400])->assertCreated();

        $charge = MonthlyCharge::first();
        $this->assertEquals('600.00', $charge->paid_amount);
        $this->assertEquals('paid', $charge->status);
    }

    public function test_overpayment_records_full_amount_and_creates_credit(): void
    {
        $this->enroll();

        // عليه 600 ودفع 1000 → الدفعة تسجل 1000 والـ 550 زايد
        $response = $this->recordPayment($this->admin, ['amount' => 1000]);
        $response->assertCreated();

        $payment = Payment::first();
        $this->assertEquals('1000.00', $payment->amount); // المسجل = الفعلي كامل

        $charge = MonthlyCharge::first();
        $this->assertEquals('600.00', $charge->paid_amount); // الـ allocation للفاتورة
        $this->assertEquals('paid', $charge->status);

        $credit = StudentCredit::first();
        $this->assertNotNull($credit);
        $this->assertEquals('400.00', $credit->remaining_amount);
    }

    public function test_credit_can_be_used_later(): void
    {
        $this->enroll();

        // شهر فات + overpayment → credit
        $this->recordPayment($this->admin, ['amount' => 1000, 'period' => $this->lastMonth()])->assertCreated();

        // الشهر الحالي: يستخدم الكريدت (400) + يكمل 200 كاش
        $response = $this->recordPayment($this->admin, [
            'amount' => 200,
            'use_credit' => true,
        ]);
        $response->assertCreated();

        $currentCharge = MonthlyCharge::forPeriod(now()->year, now()->month)->first();
        $this->assertEquals('600.00', $currentCharge->paid_amount);
        $this->assertEquals('paid', $currentCharge->status);

        // الكريدت اتفضى
        $this->assertEquals(0, (float) $this->student->credits()->sum('remaining_amount'));

        // audit بسجل استخدام الرصيد
        $this->assertDatabaseHas('financial_audit_logs', ['action' => 'credit_used']);
    }

    public function test_unpaid_charge_remains_outstanding(): void
    {
        $this->enroll();
        $this->generateCharges($this->admin);

        $charge = MonthlyCharge::first();
        $this->assertEquals('unpaid', $charge->status);
        $this->assertEquals('0.00', $charge->paid_amount);
    }

    // ---------------------------------------------------------------
    // الفواتير: auto-create و duplicates (سيناريوهان 10-11)
    // ---------------------------------------------------------------

    public function test_duplicate_monthly_charge_is_prevented(): void
    {
        $this->enroll();

        $this->generateCharges($this->admin);
        $this->generateCharges($this->admin); // تاني مرة

        $this->assertEquals(1, MonthlyCharge::count());

        // والإشعارات مش بتتضاعف — بتتبعت عن الفواتير المنشأة فعلاً بس
        $this->assertEquals(1, Notification::where('title', 'Payment Due')
            ->where('user_id', $this->student->id)
            ->count());
    }

    public function test_payment_without_existing_charge_creates_charge_automatically(): void
    {
        $this->enroll();

        // مفيش توليد فواتير خالص — الدفعة بتولد الفاتورة لوحدها
        $this->recordPayment($this->admin, ['amount' => 600])->assertCreated();

        $this->assertEquals(1, MonthlyCharge::count());
        $this->assertEquals('paid', MonthlyCharge::first()->status);
    }

    public function test_payment_requires_active_subscription(): void
    {
        // طالب مش مشترك → مفيش استحقاق مالي
        $this->recordPayment($this->admin, ['amount' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('student_id');
    }

    // ---------------------------------------------------------------
    // cancel / update (سيناريوهان 12-13)
    // ---------------------------------------------------------------

    public function test_cancel_payment_recalculates_charge(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 200])->assertCreated();
        $this->recordPayment($this->admin, ['amount' => 400])->assertCreated();

        $charge = MonthlyCharge::first();
        $this->assertEquals('paid', $charge->status);

        $payment = Payment::where('amount', 400)->first();
        $this->actingAs($this->secretary)->postJson("/api/finance/payments/{$payment->id}/cancel", [
            'cancellation_reason' => 'Student returned the money.',
        ])->assertForbidden(); // الإلغاء للأدمن فقط

        $this->actingAs($this->admin)->postJson("/api/finance/payments/{$payment->id}/cancel", [
            'cancellation_reason' => 'Student returned the money.',
        ])->assertOk();

        $charge->refresh();
        $this->assertEquals('200.00', $charge->paid_amount);
        $this->assertEquals('partially_paid', $charge->status);

        $payment->refresh();
        $this->assertEquals('cancelled', $payment->status);
        $this->assertEquals($this->admin->id, $payment->cancelled_by);

        // الإشعار وصل
        $this->assertDatabaseHas('notifications', ['title' => 'Payment Cancelled']);
    }

    public function test_update_payment_recalculates_charge(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 200])->assertCreated();

        $payment = Payment::first();

        $this->actingAs($this->admin)->putJson("/api/finance/payments/{$payment->id}", [
            'amount' => 600,
        ])->assertOk();

        $charge = MonthlyCharge::first();
        $this->assertEquals('600.00', $charge->paid_amount);
        $this->assertEquals('paid', $charge->status);

        $payment->refresh();
        $this->assertEquals('600.00', $payment->amount);

        // audit فيه before/after
        $audit = FinancialAuditLog::where('action', 'payment_updated')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertEquals('200.00', $audit->changes['amount']['before']);
        $this->assertEquals('600.00', $audit->changes['amount']['after']);
    }

    public function test_cancel_payment_requires_reason(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 600]);

        $payment = Payment::first();
        $this->actingAs($this->admin)->postJson("/api/finance/payments/{$payment->id}/cancel", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cancellation_reason');
    }

    // ---------------------------------------------------------------
    // التقارير (سيناريوهان 14-16)
    // ---------------------------------------------------------------

    public function test_dashboard_calculations_are_correct(): void
    {
        [$year, $month] = $this->period();

        // طالب دفع كامل + طالب جزئي + طالب مادفعش
        $paidStudent = $this->student;
        $partialStudent = User::factory()->student()->create();
        $unpaidStudent = User::factory()->student()->create();

        foreach ([$paidStudent, $partialStudent, $unpaidStudent] as $s) {
            $this->course->students()->attach($s->id);
            CourseSubscription::create([
                'student_id' => $s->id,
                'course_id' => $this->course->id,
                'start_date' => now()->subMonth()->toDateString(),
                'monthly_fee' => 600,
                'status' => 'active',
            ]);
        }

        $this->generateCharges($this->admin);

        $this->actingAs($this->admin)->postJson('/api/finance/payments', [
            'student_id' => $paidStudent->id, 'course_id' => $this->course->id,
            'period_year' => $year, 'period_month' => $month,
            'amount' => 600, 'method' => 'cash',
        ])->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/finance/payments', [
            'student_id' => $partialStudent->id, 'course_id' => $this->course->id,
            'period_year' => $year, 'period_month' => $month,
            'amount' => 250, 'method' => 'cash',
        ])->assertCreated();

        $dashboard = $this->actingAs($this->admin)->getJson("/api/finance/dashboard?year={$year}&month={$month}");
        $dashboard->assertOk();

        $data = $dashboard->json();
        $this->assertEquals('1800.00', $data['expected']);
        $this->assertEquals('850.00', $data['collected']);
        $this->assertEquals('950.00', $data['remaining']);
        $this->assertEquals(3, $data['students_count']);
        $this->assertEquals(1, $data['paid_students']);
        $this->assertEquals(1, $data['partially_paid_students']);
        $this->assertEquals(1, $data['unpaid_students']);
        $this->assertEquals(47.22, $data['collection_rate']);
    }

    public function test_course_financial_statistics_are_correct(): void
    {
        $this->enroll();
        $this->generateCharges($this->admin);
        $this->recordPayment($this->admin, ['amount' => 300]);

        [$year, $month] = $this->period();

        $stats = $this->actingAs($this->admin)->getJson("/api/finance/courses/{$this->course->id}?year={$year}&month={$month}");
        $stats->assertOk();

        $data = $stats->json();
        $this->assertEquals(1, $data['active_subscriptions']);
        $this->assertEquals('600.00', $data['expected']);
        $this->assertEquals('300.00', $data['collected']);
        $this->assertEquals('300.00', $data['remaining']);
        $this->assertEquals('600.00', $data['current_monthly_fee']);
    }

    public function test_student_financial_profile_is_correct(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 300]);

        $profile = $this->actingAs($this->secretary)->getJson("/api/finance/students/{$this->student->id}");
        $profile->assertOk();

        $data = $profile->json();
        $this->assertEquals('600.00', $data['totals']['total_due']);
        $this->assertEquals('300.00', $data['totals']['total_paid']);
        $this->assertEquals('300.00', $data['totals']['total_remaining']);
        $this->assertEquals('0.00', $data['totals']['current_credit']);

        // تاريخ الكورسات
        $this->assertCount(1, $data['courses']);
        $this->assertEquals('600.00', $data['courses'][0]['monthly_fee']);

        // تاريخ المدفوعات فيه من سجلها
        $this->assertCount(1, $data['payment_history']);
        $this->assertEquals($this->admin->name, $data['payment_history'][0]['recorded_by']);
        $this->assertEquals('cash', $data['payment_history'][0]['method']);
    }

    // ---------------------------------------------------------------
    // الأسعار والتاريخ (سيناريو 17)
    // ---------------------------------------------------------------

    public function test_changing_course_fee_does_not_corrupt_history(): void
    {
        $this->enroll();
        $this->generateCharges($this->admin); // فاتورة الشهر الحالي بسعر 600

        $oldCharge = MonthlyCharge::first();
        $this->assertEquals('600.00', $oldCharge->amount);

        // المدرس/الأدمن يغير السعر لـ 700
        $this->actingAs($this->admin)->postJson("/api/finance/courses/{$this->course->id}/fees", [
            'monthly_fee' => 700,
        ])->assertCreated();

        // الفاتورة القديمة زي ما هي
        $this->assertEquals('600.00', $oldCharge->fresh()->amount);

        // والاشتراك القديم كمان (snapshot)
        $this->assertEquals('600.00', $this->student->subscriptions()->first()->monthly_fee);

        // بس اشتراك جديد هياخد السعر الجديد
        $newStudent = User::factory()->student()->create();
        $this->actingAs($newStudent)->postJson("/api/courses/{$this->course->id}/join")->assertOk();
        $this->assertEquals('700.00', $newStudent->subscriptions()->first()->monthly_fee);

        // تغيير السعر ممنوع على غير الأدمن
        $this->actingAs($this->secretary)->postJson("/api/finance/courses/{$this->course->id}/fees", [
            'monthly_fee' => 800,
        ])->assertForbidden();
    }

    public function test_secretary_can_view_fees_but_only_admin_changes_them(): void
    {
        $this->actingAs($this->secretary)->getJson("/api/finance/courses/{$this->course->id}/fees")
            ->assertForbidden(); // مشاهدة الأسعار جزء من الإدارة الحساسة
    }

    // ---------------------------------------------------------------
    // استقلال الشهور (سيناريو 18)
    // ---------------------------------------------------------------

    public function test_different_months_remain_financially_independent(): void
    {
        $this->enroll();

        // دفع الشهر الفات كامل
        $this->recordPayment($this->admin, ['amount' => 600, 'period' => $this->lastMonth()])->assertCreated();

        // الشهر الحالي لسه مادفعش — والفات مش متأثر
        $this->recordPayment($this->admin, ['amount' => 100])->assertCreated();

        $lastCharge = MonthlyCharge::where('period_year', now()->subMonth()->year)
            ->where('period_month', now()->subMonth()->month)->first();
        $this->assertEquals('paid', $lastCharge->status);

        $currentCharge = MonthlyCharge::forPeriod(now()->year, now()->month)->first();
        $this->assertEquals('partially_paid', $currentCharge->status);
    }

    // ---------------------------------------------------------------
    // الاشتراكات (دورة الحياة)
    // ---------------------------------------------------------------

    public function test_join_creates_subscription_with_current_fee(): void
    {
        $subscription = $this->enroll();

        $this->assertNotNull($subscription);
        $this->assertEquals('600.00', $subscription->monthly_fee);
        $this->assertEquals('active', $subscription->status);

        // اشتراك تاني لنفس الكورس مرفوض
        $this->postJson("/api/courses/{$this->course->id}/join")->assertConflict();
        $this->assertEquals(1, CourseSubscription::count());
    }

    public function test_leave_cancels_subscription_and_keeps_history(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 300]);

        $this->actingAs($this->student)->deleteJson("/api/courses/{$this->course->id}/leave")->assertOk();

        $subscription = $this->student->subscriptions()->first();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->end_date);

        // التاريخ المالي موجود لسه
        $this->assertEquals(1, MonthlyCharge::count());
        $this->assertEquals(1, Payment::count());
    }

    public function test_free_course_join_creates_no_subscription(): void
    {
        $freeCourse = Course::factory()->create(['teacher_id' => $this->teacher->id]);
        // مفيش course_fees → مفيش اشتراك مالي

        $this->actingAs($this->student)->postJson("/api/courses/{$freeCourse->id}/join")->assertOk();

        $this->assertTrue($freeCourse->students()->where('student_id', $this->student->id)->exists());
        $this->assertEquals(0, CourseSubscription::count());
    }

    public function test_admin_can_create_subscription_manually_with_discount(): void
    {
        $this->actingAs($this->admin)->postJson('/api/finance/subscriptions', [
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'monthly_discount' => 100,
            'discount_reason' => 'Sibling discount',
        ])->assertCreated();

        $subscription = $this->student->subscriptions()->first();
        $this->assertEquals('100.00', $subscription->monthly_discount);
        $this->assertEquals('Sibling discount', $subscription->discount_reason);
        $this->assertEquals('500.00', $subscription->final_monthly_amount);

        // الخصم بدون سبب مرفوض
        $student2 = User::factory()->student()->create();
        $this->actingAs($this->admin)->postJson('/api/finance/subscriptions', [
            'student_id' => $student2->id,
            'course_id' => $this->course->id,
            'monthly_discount' => 50,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('discount_reason');

        // الخصم أكبر من السعر مرفوض
        $this->actingAs($this->admin)->postJson('/api/finance/subscriptions', [
            'student_id' => $student2->id,
            'course_id' => $this->course->id,
            'monthly_discount' => 9999,
            'discount_reason' => 'Crazy',
        ])->assertUnprocessable();
    }

    // ---------------------------------------------------------------
    // المتأخرات + الـ audit + الإشعارات
    // ---------------------------------------------------------------

    public function test_overdue_returns_students_with_outstanding_balances(): void
    {
        $this->enroll();
        $this->generateCharges($this->admin);
        $this->recordPayment($this->admin, ['amount' => 100]);

        $overdue = $this->actingAs($this->secretary)->getJson('/api/finance/overdue');
        $overdue->assertOk();

        $data = $overdue->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->student->id, $data[0]['student']['id']);
        $this->assertEquals('partially_paid', $data[0]['status']);

        // فلتر بالكورس — الكورس التاني مالوش متأخرات
        $otherCourse = Course::factory()->create();
        $empty = $this->actingAs($this->secretary)->getJson("/api/finance/overdue?course_id={$otherCourse->id}");
        $this->assertCount(0, $empty->json('data'));
    }

    public function test_audit_log_tracks_payment_lifecycle(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 600]);

        $this->assertDatabaseHas('financial_audit_logs', [
            'action' => 'payment_created',
            'actor_id' => $this->admin->id,
        ]);

        $payment = Payment::first();
        $this->actingAs($this->admin)->postJson("/api/finance/payments/{$payment->id}/cancel", [
            'cancellation_reason' => 'Test cancel',
        ])->assertOk();

        $this->assertDatabaseHas('financial_audit_logs', [
            'action' => 'payment_cancelled',
        ]);

        // endpoint الـ audit للأدمن فقط
        $this->actingAs($this->secretary)->getJson('/api/finance/audit-logs')->assertForbidden();
        $logs = $this->actingAs($this->admin)->getJson('/api/finance/audit-logs');
        $logs->assertOk();
        $this->assertGreaterThanOrEqual(2, count($logs->json('data')));
    }

    public function test_charges_generation_notifies_students(): void
    {
        $this->enroll();

        $this->generateCharges($this->admin);

        [$year, $month] = $this->period();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id,
            'title' => 'Payment Due',
        ]);

        $notification = Notification::where('user_id', $this->student->id)->where('title', 'Payment Due')->first();
        $this->assertStringContainsString('is due for payment', $notification->message);
    }

    public function test_payment_recording_notifies_student(): void
    {
        $this->enroll();
        $this->recordPayment($this->admin, ['amount' => 600]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->student->id,
            'title' => 'Payment Recorded',
        ]);
    }

    // ---------------------------------------------------------------
    // Concurrency protection (سيناريو 20)
    // ---------------------------------------------------------------

    public function test_rapid_concurrent_payments_cannot_corrupt_balances(): void
    {
        $this->enroll();

        // محاكاة دفعات متداخلة سريعة على نفس الفاتورة
        $responses = [];
        foreach ([200, 200, 200] as $amount) {
            $responses[] = $this->recordPayment($this->admin, ['amount' => $amount]);
        }

        foreach ($responses as $response) {
            $response->assertCreated();
        }

        $charge = MonthlyCharge::first();
        // 600 = المبلغ، تلات دفعات × 200 — مجموع allocations ميزيدش عن الفاتورة
        $this->assertEquals('600.00', $charge->paid_amount);
        $this->assertEquals('paid', $charge->status);

        $this->assertEquals(600, (float) Payment::where('status', 'active')->sum('amount'));
    }

    public function test_db_transactions_rollback_on_failure(): void
    {
        $this->enroll();

        // دفعة لطالب مش مسجل في كورس تاني → العملية كلها تترفض من غير أي أثر
        $otherStudent = User::factory()->student()->create();
        $before = Payment::count();

        $this->actingAs($this->admin)->postJson('/api/finance/payments', [
            'student_id' => $otherStudent->id,
            'course_id' => $this->course->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'amount' => 100,
            'method' => 'cash',
        ])->assertUnprocessable();

        $this->assertEquals($before, Payment::count());
        $this->assertEquals(0, MonthlyCharge::count());
    }

    // ---------------------------------------------------------------
    // Audit trail — إلغاء الاشتراكات لازم يتسجل
    // ---------------------------------------------------------------

    public function test_student_leave_creates_subscription_cancelled_audit(): void
    {
        $this->actingAs($this->student)->postJson("/api/courses/{$this->course->id}/join")
            ->assertOk();

        $subscription = CourseSubscription::where('student_id', $this->student->id)->first();

        // الطالب يسيب الكورس
        $this->actingAs($this->student)->deleteJson("/api/courses/{$this->course->id}/leave")
            ->assertOk();

        // الإلغاء ايتسجل: المين + أنهي اشتراك + إيه اللي اتغير + إمتى
        $this->assertDatabaseHas('financial_audit_logs', [
            'action' => 'subscription_cancelled',
            'actor_id' => $this->student->id,
            'auditable_type' => CourseSubscription::class,
            'auditable_id' => $subscription->id,
        ]);

        $log = FinancialAuditLog::where('action', 'subscription_cancelled')
            ->where('auditable_id', $subscription->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log->created_at);
        $this->assertEquals('active', $log->changes['before']['status']);
        $this->assertEquals('cancelled', $log->changes['after']['status']);
    }

    public function test_admin_force_removal_creates_cancelled_audit_with_admin_actor(): void
    {
        $this->actingAs($this->admin)->postJson('/api/finance/subscriptions', [
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
        ])->assertCreated();

        $subscription = CourseSubscription::where('student_id', $this->student->id)->first();

        // الإنشاء ايتسجل بالأدمن
        $this->assertDatabaseHas('financial_audit_logs', [
            'action' => 'subscription_created',
            'actor_id' => $this->admin->id,
            'auditable_id' => $subscription->id,
        ]);

        // الأدمن يشال الطالب بالقوة → إلغاء مسجل باسمه
        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/courses/{$this->course->id}/students/{$this->student->id}")
            ->assertOk();

        $this->assertDatabaseHas('financial_audit_logs', [
            'action' => 'subscription_cancelled',
            'actor_id' => $this->admin->id,
            'auditable_type' => CourseSubscription::class,
            'auditable_id' => $subscription->id,
        ]);
    }

    public function test_subscription_cancellation_records_cancellation_reason_if_provided(): void
    {
        $subscription = $this->enroll();

        $this->actingAs($this->admin)->putJson("/api/finance/subscriptions/{$subscription->id}", [
            'status' => 'cancelled',
            'cancellation_reason' => 'Student relocated to another city.',
        ])->assertOk();

        $log = FinancialAuditLog::where('action', 'subscription_cancelled')
            ->where('auditable_id', $subscription->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Student relocated to another city.', $log->changes['after']['cancellation_reason']);
    }
}

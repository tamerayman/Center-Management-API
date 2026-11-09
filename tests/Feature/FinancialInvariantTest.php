<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseFee;
use App\Models\CourseSubscription;
use App\Models\MonthlyCharge;
use App\Models\Payment;
use App\Models\StudentCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Financial Invariants Audit — بعد كل نوع عملية مالية، المعادلات لازم تفضل صح:
 *
 * I1) paid_amount = Σ(active payment allocations) + credit_applied   [لكل فاتورة]
 * I2) حالة الفاتورة متسقة مع paid_amount مقابل amount
 * I3) دفعة نشطة: amount = allocated + Σ(credits الناشئة منها)        [الف计量 محفوظ]
 * I4) remaining ≤ amount لكل credit
 * I5) credit من دفعة ملغاة: remaining = 0 (متبقي ميت — الإلغاء بيبطله بالكامل)
 * I6) Σ(credit_used) = Σ(credit_applied على الفواتير) + Σ(voided بإلغاء)
 */
class FinancialInvariantTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->create();

        CourseFee::create([
            'course_id' => $this->course->id,
            'monthly_fee' => 500,
            'effective_from' => now()->subMonths(3)->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $this->course->students()->attach($this->student->id);
        CourseSubscription::create([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'start_date' => now()->subMonths(3)->toDateString(),
            'monthly_fee' => 500,
            'status' => 'active',
        ]);
    }

    private function pay(float $amount, string $period, array $extra = []): void
    {
        [$y, $m] = explode('-', $period);

        $response = $this->actingAs($this->admin)->postJson('/api/finance/payments', array_merge([
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'period_year' => (int) $y,
            'period_month' => (int) $m,
            'amount' => $amount,
            'method' => 'cash',
        ], $extra));

        if ($response->status() !== 201) {
            fwrite(STDERR, "\nPAY $amount ($period) → {$response->status()}: ".$response->getContent()."\n");
        }

        $response->assertCreated();
    }

    private function period(int $monthsAgo = 0): string
    {
        return now()->subMonths($monthsAgo)->format('Y-n');
    }

    /**
     * الفحص الشامل — بيتنادى بعد كل عملية.
     */
    private function assertFinancialInvariants(): void
    {
        $charges = MonthlyCharge::with('payments')->get();
        $credits = StudentCredit::all();

        // I1 + I2: كل فاتورة
        foreach ($charges as $charge) {
            $expectedPaid = (float) $charge->payments
                ->where('status', 'active')
                ->sum('allocated_amount') + (float) $charge->credit_applied;

            $this->assertEquals(
                round($expectedPaid, 2),
                round((float) $charge->paid_amount, 2),
                "I1 violated on charge #{$charge->id}: paid_amount should equal active allocations + credit_applied"
            );

            $status = match (true) {
                (float) $charge->paid_amount >= (float) $charge->amount && (float) $charge->amount > 0 => 'paid',
                (float) $charge->paid_amount > 0 => 'partially_paid',
                default => 'unpaid',
            };

            $this->assertEquals(
                $status,
                $charge->status,
                "I2 violated on charge #{$charge->id}: status '{$charge->status}' inconsistent with paid={$charge->paid_amount}/{$charge->amount}"
            );
        }

        // I3 + I4 + I5: كل credit
        $totalUsed = 0.0;
        $totalVoided = 0.0;

        foreach ($credits as $credit) {
            $this->assertLessThanOrEqual(
                (float) $credit->amount,
                (float) $credit->remaining_amount,
                "I4 violated on credit #{$credit->id}: remaining exceeds amount"
            );

            $source = Payment::find($credit->source_payment_id);

            if ($source && $source->status === 'active') {
                $creditFromSource = (float) StudentCredit::where('source_payment_id', $source->id)->sum('amount');

                $this->assertEquals(
                    round((float) $source->amount, 2),
                    round((float) $source->allocated_amount + $creditFromSource, 2),
                    "I3 violated on payment #{$source->id}: amount != allocated + credits"
                );
            }

            if ($source && $source->status === 'cancelled') {
                $this->assertEquals(
                    0.0,
                    (float) $credit->remaining_amount,
                    "I5 violated on credit #{$credit->id}: cancelled payment's credit must be fully voided"
                );
                $totalVoided += (float) $credit->amount;
            }

            $totalUsed += (float) $credit->amount - (float) $credit->remaining_amount;
        }

        // I6: كل استخدام credit إما مطبق على فاتورة أو ملغي بإلغاء دفعته
        $totalApplied = (float) MonthlyCharge::sum('credit_applied');

        $this->assertEquals(
            round($totalApplied, 2),
            round($totalUsed - $totalVoided, 2),
            'I6 violated: credit usage must equal credit applied to charges + voided by cancellation'
        );
    }

    /** @test */
    public function invariants_hold_through_full_lifecycle(): void
    {
        // 1) partial payment
        $this->pay(200, $this->period());
        $this->assertFinancialInvariants();

        // 2) تكملة الشهر
        $this->pay(300, $this->period());
        $this->assertFinancialInvariants();

        // 3) overpayment على شهر تاني → credit
        $this->pay(1000, $this->period(1));
        $this->assertFinancialInvariants();

        // 4) استخدام الـ credit على شهر تالت
        $this->pay(0, $this->period(2), ['use_credit' => true]);
        $this->assertFinancialInvariants();

        // 5) تعديل دفعة
        $payment = Payment::where('amount', 200)->first();
        $this->actingAs($this->admin)->putJson("/api/finance/payments/{$payment->id}", [
            'amount' => 500,
        ])->assertOk();
        $this->assertFinancialInvariants();

        // 6) إلغاء دفعة بدون credit مرتبط (دفع الـ 1000 الـ credit بتاعه مستخدم → الإلغاء متمنع بالقاعدة الآمنة)
        $payment2 = Payment::where('amount', 300)->first();
        $this->actingAs($this->admin)->postJson("/api/finance/payments/{$payment2->id}/cancel", [
            'cancellation_reason' => 'Audit lifecycle test',
        ])->assertOk();
        $this->assertFinancialInvariants();

        // حماية إضافية: إلغاء دفعة الـ credit المستخدم مرفوض
        $paymentWithCredit = Payment::where('amount', 1000)->first();
        $this->actingAs($this->admin)->postJson("/api/finance/payments/{$paymentWithCredit->id}/cancel", [
            'cancellation_reason' => 'Should be blocked — credit already applied',
        ])->assertUnprocessable();
        $this->assertFinancialInvariants();

        // 7) إلغاء الاشتراك — التاريخ بيفضل متسق
        $subscription = CourseSubscription::first();
        $this->actingAs($this->admin)->putJson("/api/finance/subscriptions/{$subscription->id}", [
            'status' => 'cancelled',
        ])->assertOk();
        $this->assertFinancialInvariants();

        // 8) دفعة بعد إلغاء الاشتراك مرفوضة (مفيش استحقاق جديد)
        $this->actingAs($this->admin)->postJson('/api/finance/payments', [
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'amount' => 100,
            'method' => 'cash',
        ])->assertUnprocessable();
        $this->assertFinancialInvariants();

        // الشهر الحالي: كان 500 → بعد إلغاء الـ 300 رجع 200 جزئي
        $currentCharge = MonthlyCharge::forPeriod(now()->year, now()->month)->first();
        $this->assertEquals('200.00', $currentCharge->paid_amount);
        $this->assertEquals('partially_paid', $currentCharge->status);
    }

    /** @test */
    public function invariants_hold_with_multiple_students_and_courses(): void
    {
        $student2 = User::factory()->student()->create();
        $course2 = Course::factory()->create();

        CourseFee::create([
            'course_id' => $course2->id,
            'monthly_fee' => 300,
            'effective_from' => now()->subMonth()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $this->course->students()->attach($student2->id);
        $course2->students()->attach($this->student->id);
        $course2->students()->attach($student2->id);

        // اشتراكات مالية لكل (طالب، كورس)
        CourseSubscription::create([
            'student_id' => $student2->id,
            'course_id' => $this->course->id,
            'start_date' => now()->subMonth()->toDateString(),
            'monthly_fee' => 500,
            'status' => 'active',
        ]);

        foreach ([$this->student, $student2] as $s) {
            CourseSubscription::create([
                'student_id' => $s->id,
                'course_id' => $course2->id,
                'start_date' => now()->subMonth()->toDateString(),
                'monthly_fee' => 300,
                'status' => 'active',
            ]);
        }

        // موجة عمليات متداخلة على الاتنين
        $this->pay(100, $this->period());
        $this->pay(700, $this->period(), ['use_credit' => false]);
        $this->assertFinancialInvariants();

        $this->actingAs($this->admin)->postJson('/api/finance/payments', [
            'student_id' => $student2->id,
            'course_id' => $this->course->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'amount' => 500,
            'method' => 'cash',
        ])->assertCreated();
        $this->assertFinancialInvariants();

        $this->actingAs($this->admin)->postJson('/api/finance/payments', [
            'student_id' => $student2->id,
            'course_id' => $course2->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'amount' => 50,
            'method' => 'vodafone_cash',
        ])->assertCreated();
        $this->assertFinancialInvariants();

        // إلغاء في النص
        $payment = Payment::where('amount', 500)->first();
        $this->actingAs($this->admin)->postJson("/api/finance/payments/{$payment->id}/cancel", [
            'cancellation_reason' => 'Duplicate entry',
        ])->assertOk();
        $this->assertFinancialInvariants();
    }
}

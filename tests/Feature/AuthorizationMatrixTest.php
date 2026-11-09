<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseFee;
use App\Models\CourseSubscription;
use App\Models\MonthlyCharge;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorization Matrix Audit — endpoint by endpoint × 5 roles.
 * كل endpoint بيتقصد بالـ IDs الحقيقية (كورس المدرس A، دفعة حقيقية...) عشان
 * الحكم يكون على الـ authorization الفعلي مش على 404/401 مصادفة.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $secretary;

    private User $teacherA;

    private User $teacherB;

    private User $student;

    private Course $courseA; // كورس المدرس A — عليه اشتراك وفاتورة ودفعة حقيقية

    private Course $courseB; // كورس المدرس B

    private MonthlyCharge $charge;

    private Payment $payment;

    private CourseSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->secretary = User::factory()->secretary()->create();
        $this->teacherA = User::factory()->teacher()->create();
        $this->teacherB = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();

        $this->courseA = Course::factory()->create(['teacher_id' => $this->teacherA->id]);
        $this->courseB = Course::factory()->create(['teacher_id' => $this->teacherB->id]);

        CourseFee::create([
            'course_id' => $this->courseA->id,
            'monthly_fee' => 500,
            'effective_from' => now()->subMonth()->toDateString(),
            'created_by' => $this->admin->id,
        ]);

        $this->subscription = CourseSubscription::create([
            'student_id' => $this->student->id,
            'course_id' => $this->courseA->id,
            'start_date' => now()->subMonth()->toDateString(),
            'monthly_fee' => 500,
            'status' => 'active',
        ]);
        $this->courseA->students()->attach($this->student->id);

        $this->charge = MonthlyCharge::create([
            'subscription_id' => $this->subscription->id,
            'student_id' => $this->student->id,
            'course_id' => $this->courseA->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'amount' => 500,
        ]);

        $this->payment = Payment::create([
            'student_id' => $this->student->id,
            'course_id' => $this->courseA->id,
            'monthly_charge_id' => $this->charge->id,
            'amount' => 200,
            'allocated_amount' => 200,
            'paid_at' => today(),
            'method' => 'cash',
            'created_by' => $this->admin->id,
        ]);
    }

    /** @test */
    public function authorization_matrix(): void
    {
        $year = now()->year;
        $month = now()->month;
        $sid = $this->student->id;

        // [method, url, body, [admin, secretary, teacherA, teacherB, student]]
        $matrix = [
            // ---- المشاهدات العامة المالية: staff فقط، المدرس كورساته بس (scoped 200) ----
            'GET /finance/dashboard' => ['GET', "/api/finance/dashboard?year={$year}&month={$month}", null, [200, 200, 403, 403, 403]],
            'GET /finance/overdue' => ['GET', '/api/finance/overdue', null, [200, 200, 403, 403, 403]],
            'GET /finance/reports/monthly' => ['GET', '/api/finance/reports/monthly?year='.$year, null, [200, 200, 403, 403, 403]],
            'GET /finance/reports/collection' => ['GET', "/api/finance/reports/collection?year={$year}&month={$month}", null, [200, 200, 403, 403, 403]],
            'GET /finance/audit-logs' => ['GET', '/api/finance/audit-logs', null, [200, 403, 403, 403, 403]],

            // ---- الاشتراكات: مشاهدة scoped للمدرس، الكتابة أدمن فقط ----
            'GET /finance/subscriptions' => ['GET', '/api/finance/subscriptions', null, [200, 200, 200, 200, 403]],
            'POST /finance/subscriptions' => ['POST', '/api/finance/subscriptions', [
                'student_id' => $sid, 'course_id' => $this->courseA->id,
            ], [409, 403, 403, 403, 403]], // الأدمن 409 (اشتراك active موجود أصلاً — عدّى الـ gate)
            'PUT /finance/subscriptions/{id}' => ['PUT', "/api/finance/subscriptions/{$this->subscription->id}", [
                'status' => 'paused',
            ], [200, 403, 403, 403, 403]],

            // ---- الفواتير ----
            'GET /finance/charges' => ['GET', '/api/finance/charges', null, [200, 200, 200, 200, 403]],
            'POST /finance/charges/generate' => ['POST', '/api/finance/charges/generate', [
                'period_year' => $year, 'period_month' => $month,
            ], [200, 403, 403, 403, 403]],

            // ---- المدفوعات ----
            'GET /finance/payments' => ['GET', '/api/finance/payments', null, [200, 200, 200, 200, 403]],
            'POST /finance/payments (courseA)' => ['POST', '/api/finance/payments', [
                'student_id' => $sid, 'course_id' => $this->courseA->id,
                'period_year' => $year, 'period_month' => $month,
                'amount' => 50, 'method' => 'cash',
            ], [201, 201, 201, 403, 403]],
            'POST /finance/payments (courseB)' => ['POST', '/api/finance/payments', [
                'student_id' => $sid, 'course_id' => $this->courseB->id,
                'period_year' => $year, 'period_month' => $month,
                'amount' => 50, 'method' => 'cash',
            ], [422, 422, 403, 422, 403]], // staff: 422 (مفيش اشتراك) — teacherA: 403 مش كورسه
            'PUT /finance/payments/{id}' => ['PUT', "/api/finance/payments/{$this->payment->id}", [
                'note' => 'x',
            ], [200, 403, 403, 403, 403]],
            'POST /finance/payments/{id}/cancel' => ['POST', "/api/finance/payments/{$this->payment->id}/cancel", [
                'cancellation_reason' => 'audit test',
            ], [200, 403, 403, 403, 403]],

            // ---- الملفات المالية ----
            'GET /finance/students/{id}' => ['GET', "/api/finance/students/{$sid}", null, [200, 200, 200, 403, 403]],
            'GET /finance/courses/{courseA}' => ['GET', "/api/finance/courses/{$this->courseA->id}", null, [200, 200, 200, 403, 403]],
            'GET /finance/courses/{courseB}' => ['GET', "/api/finance/courses/{$this->courseB->id}", null, [200, 200, 403, 200, 403]],
            'GET /finance/courses/{courseA}/fees' => ['GET', "/api/finance/courses/{$this->courseA->id}/fees", null, [200, 403, 403, 403, 403]],
            'POST /finance/courses/{courseA}/fees' => ['POST', "/api/finance/courses/{$this->courseA->id}/fees", [
                'monthly_fee' => 700,
            ], [201, 403, 403, 403, 403]],

            // ---- إدارة المستخدمين (admin فقط) ----
            'GET /admin/users' => ['GET', '/api/admin/users', null, [200, 403, 403, 403, 403]],
            'POST /admin/users' => ['POST', '/api/admin/users', [
                'name' => 'X', 'email' => 'x'.uniqid().'@test.com', 'password' => 'password123', 'role' => 'student',
            ], [201, 403, 403, 403, 403]],

            // ---- المحتوى: teacher middleware ----
            'POST /courses (create)' => ['POST', '/api/courses', [
                'title' => 'New Course',
            ], [403, 403, 201, 201, 403]], // الأدمن عنده تجاوز في الـ policies لكن مش منشئ كورس لنفسه — create تتطلب teacher
            'GET /finance/charges (student join flow)' => ['GET', '/api/my-courses', null, [200, 200, 200, 200, 200]],
        ];

        $roles = [
            'admin' => $this->admin,
            'secretary' => $this->secretary,
            'teacherA' => $this->teacherA,
            'teacherB' => $this->teacherB,
            'student' => $this->student,
        ];

        $failures = [];

        foreach ($matrix as $label => [$method, $url, $body, $expected]) {
            foreach ($roles as $roleName => $user) {
                $expectedStatus = $expected[array_search($roleName, array_keys($roles))];

                $request = $this->actingAs($user)->json($method, $url, $body ?? []);
                $actual = $request->getStatusCode();

                if ($actual !== $expectedStatus) {
                    $failures[] = sprintf(
                        '[%s] %s as %s → expected %d got %d',
                        $label, $method, $roleName, $expectedStatus, $actual
                    );
                }
            }
        }

        // تقرير مفصل يطبع في الـ output لو حاجة فشلت
        $this->assertSame([], $failures, "Authorization matrix failures:\n".implode("\n", $failures));
    }

    /** @test */
    public function guest_gets_401_on_all_financial_endpoints(): void
    {
        $endpoints = [
            ['GET', '/api/finance/dashboard'],
            ['GET', '/api/finance/subscriptions'],
            ['GET', '/api/finance/charges'],
            ['GET', '/api/finance/payments'],
            ['GET', '/api/finance/overdue'],
            ['GET', '/api/finance/reports/monthly'],
            ['GET', '/api/finance/audit-logs'],
            ['POST', '/api/finance/payments'],
            ['POST', '/api/finance/charges/generate'],
        ];

        foreach ($endpoints as [$method, $url]) {
            $this->json($method, $url)->assertUnauthorized();
        }
    }

    /** @test */
    public function teacher_scoping_returns_only_his_own_data(): void
    {
        // طالب تاني في كورس المدرس B عليه فاتورة
        $studentB = User::factory()->student()->create();
        $subB = CourseSubscription::create([
            'student_id' => $studentB->id,
            'course_id' => $this->courseB->id,
            'start_date' => now()->subMonth()->toDateString(),
            'monthly_fee' => 300,
            'status' => 'active',
        ]);
        MonthlyCharge::create([
            'subscription_id' => $subB->id,
            'student_id' => $studentB->id,
            'course_id' => $this->courseB->id,
            'period_year' => now()->year,
            'period_month' => now()->month,
            'amount' => 300,
        ]);

        // teacherA يشوف فواتير كورسه بس
        $response = $this->actingAs($this->teacherA)->getJson('/api/finance/charges');
        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->courseA->id, $row['course']['id']);
        }

        // teacherB يشوف فواتير كورسه بس (ومش بتاعة A)
        $responseB = $this->actingAs($this->teacherB)->getJson('/api/finance/charges');
        $responseB->assertOk();
        foreach ($responseB->json('data') as $row) {
            $this->assertEquals($this->courseB->id, $row['course']['id']);
        }
    }
}

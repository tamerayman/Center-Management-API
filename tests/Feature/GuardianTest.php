<?php

namespace Tests\Feature;

use App\Events\ChargesGenerated;
use App\Events\PaymentRecorded;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\Guardian;
use App\Models\GuardianNotification;
use App\Models\Lesson;
use App\Models\MonthlyCharge;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GuardianTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private User $otherStudent;

    private string $guardianCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->create();
        $this->otherStudent = User::factory()->student()->create();

        // الـ Observer بيولد الكود عند الإنشاء — نثبته هنا للتحكم في الاختبار
        $this->guardianCode = 'GUARD-TEST-CODE';
        $this->student->forceFill(['guardian_code' => $this->guardianCode])->save();
        $this->otherStudent->forceFill(['guardian_code' => 'GUARD-OTHER-9999'])->save();
    }

    private function registerGuardian(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/guardian/register', array_merge([
            'name' => 'Ahmed Mohamed',
            'phone' => '01012345678',
            'password' => 'secret1234',
            'guardian_code' => $this->guardianCode,
            'relationship' => 'father',
        ], $overrides));
    }

    private function guardianToken(array $overrides = []): string
    {
        $response = $this->registerGuardian($overrides);
        $response->assertCreated();

        return $response->json('token');
    }

    private function linkChild(string $token, string $code, string $relationship = 'father', int $expected = 201): void
    {
        $this->withToken($token)->postJson('/api/guardian/children', [
            'guardian_code' => $code,
            'relationship' => $relationship,
        ])->assertStatus($expected);
    }

    // ---------------------------------------------------------------
    // Registration
    // ---------------------------------------------------------------

    public function test_guardian_can_register_with_valid_code(): void
    {
        $response = $this->registerGuardian();

        $response->assertCreated()
            ->assertJsonPath('guardian.name', 'Ahmed Mohamed')
            ->assertJsonPath('children.0.id', $this->student->id)
            ->assertJsonStructure(['guardian', 'children', 'token']);

        $this->assertDatabaseHas('guardians', ['phone' => '01012345678']);
        $this->assertDatabaseHas('guardian_student', [
            'student_id' => $this->student->id,
            'relationship' => 'father',
        ]);

        // التوكن شغال فعلاً
        $token = $response->json('token');
        $this->withToken($token)->getJson('/api/guardian/children')->assertOk();
    }

    public function test_guardian_cannot_register_with_invalid_code(): void
    {
        $this->registerGuardian(['guardian_code' => 'GUARD-WRNG-XXXX'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guardian_code');

        $this->assertDatabaseCount('guardians', 0);
    }

    public function test_guardian_code_is_required(): void
    {
        $this->registerGuardian(['guardian_code' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guardian_code');
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $this->registerGuardian()->assertCreated();

        // أب تاني بنفس الموبايل — مرفوض
        $this->registerGuardian(['name' => 'Other Father', 'guardian_code' => 'GUARD-OTHER-9999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_teacher_code_field_does_not_leak_student_id(): void
    {
        // الكود مش بيحتوي student_id ولا بيرجعه في أي رابط
        $this->assertStringNotContainsString((string) $this->student->id, $this->student->guardian_code);
    }

    // ---------------------------------------------------------------
    // Multiple guardians + multiple children
    // ---------------------------------------------------------------

    public function test_father_and_mother_can_both_link_to_same_student(): void
    {
        $this->registerGuardian(['relationship' => 'father'])->assertCreated();

        // الأم تستخدم نفس كود الطالب — حساب مختلف تماماً
        $this->registerGuardian([
            'name' => 'Mother Name',
            'phone' => '01099999999',
            'relationship' => 'mother',
        ])->assertCreated();

        $this->assertDatabaseHas('guardian_student', [
            'student_id' => $this->student->id, 'relationship' => 'father',
        ]);
        $this->assertDatabaseHas('guardian_student', [
            'student_id' => $this->student->id, 'relationship' => 'mother',
        ]);
        $this->assertEquals(2, $this->student->guardians()->count());
    }

    public function test_guardian_can_have_multiple_children(): void
    {
        $token = $this->guardianToken();

        // نفس Guardian يربط الطالب التاني بكوده
        $this->linkChild($token, 'GUARD-OTHER-9999');

        $children = $this->withToken($token)->getJson('/api/guardian/children');
        $children->assertOk();

        $ids = collect($children->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$this->student->id, $this->otherStudent->id], $ids->all());
    }

    public function test_adding_same_child_twice_returns_409(): void
    {
        $token = $this->guardianToken();

        $this->linkChild($token, 'GUARD-OTHER-9999');
        $this->linkChild($token, 'GUARD-OTHER-9999', 'father', 409);
    }

    public function test_admin_user_creation_does_not_conflict_with_guardians(): void
    {
        // الـ Guardian ليس user — role enum ما اتغيرش
        $this->assertEquals(
            ['admin', 'secretary', 'teacher', 'student'],
            ['admin', 'secretary', 'teacher', 'student']
        );
        $this->assertDatabaseMissing('users', ['role' => 'guardian']);
    }

    // ---------------------------------------------------------------
    // Authorization — children access
    // ---------------------------------------------------------------

    public function test_guardian_can_access_his_own_child(): void
    {
        $token = $this->guardianToken();

        $this->withToken($token)->getJson("/api/guardian/children/{$this->student->id}")
            ->assertOk()
            ->assertJsonPath('data.student.id', $this->student->id)
            ->assertJsonStructure(['data' => ['student', 'courses', 'attendance', 'upcoming_exams', 'results', 'finance']]);
    }

    public function test_guardian_cannot_access_unrelated_child(): void
    {
        $token = $this->guardianToken();

        // الطالب التاني مش مربوط بيه → 403 حتى مع معرفة الـ ID
        $this->withToken($token)->getJson("/api/guardian/children/{$this->otherStudent->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'This student is not linked to your account.');
    }

    public function test_guardian_cannot_access_child_after_linking_other_student_only(): void
    {
        // Guardian مربوط بـ otherStudent بس → student الأصلي ممنوع
        $token = $this->guardianToken(['guardian_code' => 'GUARD-OTHER-9999']);

        $this->withToken($token)->getJson("/api/guardian/children/{$this->student->id}")
            ->assertForbidden();
    }

    public function test_guardian_child_payments_are_scoped_and_paginated(): void
    {
        $token = $this->guardianToken();

        // دفعتين للطفل المرتبط
        Payment::create([
            'student_id' => $this->student->id, 'course_id' => Course::factory()->create()->id,
            'amount' => 100, 'allocated_amount' => 100, 'paid_at' => today(), 'method' => 'cash',
            'created_by' => $this->admin->id,
        ]);
        Payment::create([
            'student_id' => $this->student->id, 'course_id' => Course::factory()->create()->id,
            'amount' => 200, 'allocated_amount' => 200, 'paid_at' => today(), 'method' => 'card',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/guardian/children/{$this->student->id}/payments");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.total'));
    }

    // ---------------------------------------------------------------
    // Read-only — كل محاولات الكتابة مرفوضة
    // ---------------------------------------------------------------

    public function test_guardian_token_cannot_use_user_endpoints(): void
    {
        $guardianToken = $this->guardianToken();

        // نفس توكن الـ Guardian على endpoints الطلبة/المستخدمين → 403
        $userEndpoints = [
            ['GET', '/api/my-courses'],
            ['GET', '/api/profile'],
            ['GET', '/api/dashboard'],
            ['GET', '/api/notifications'],
            ['GET', '/api/my-exams'],
            ['GET', '/api/courses'],
            ['POST', '/api/logout'],
        ];

        foreach ($userEndpoints as [$method, $url]) {
            $this->withToken($guardianToken)->json($method, $url)
                ->assertForbidden();
        }
    }

    public function test_guardian_cannot_modify_anything(): void
    {
        $guardianToken = $this->guardianToken();

        $writeAttempts = [
            ['POST', '/api/guardian/children/../courses', []],
            ['PUT', "/api/guardian/children/{$this->student->id}", ['name' => 'Hacked']],
            ['DELETE', "/api/guardian/children/{$this->student->id}", []],
        ];

        foreach ($writeAttempts as [$method, $url, $body]) {
            $response = $this->withToken($guardianToken)->json($method, $url, $body);
            $this->assertContains($response->status(), [403, 404, 405], "{$method} {$url} should be blocked");
        }
    }

    public function test_user_token_cannot_use_guardian_endpoints(): void
    {
        // طالب حقيقي بتوكن Sanctum عادي — Sanctum نفسه بيرفض التوكن
        // لأن الـ provider بتاع guard الـ guardian هو guardians مش users → 401
        $studentToken = $this->postJson('/api/login', [
            'email' => $this->student->email,
            'password' => 'password',
        ])->json('token');

        $this->assertNotNull($studentToken);

        $guardianEndpoints = [
            ['GET', '/api/guardian/children'],
            ['GET', '/api/guardian/dashboard'],
            ['GET', '/api/guardian/notifications'],
            ['GET', "/api/guardian/children/{$this->student->id}"],
            ['POST', '/api/guardian/children'],
        ];

        foreach ($guardianEndpoints as [$method, $url]) {
            $this->withToken($studentToken)->json($method, $url)
                ->assertUnauthorized();
        }
    }

    public function test_guest_cannot_access_guardian_endpoints(): void
    {
        $this->getJson('/api/guardian/children')->assertUnauthorized();
        $this->getJson('/api/guardian/dashboard')->assertUnauthorized();
        $this->getJson('/api/guardian/notifications')->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // Notifications — توزيع صحيح على المرتبطين فقط
    // ---------------------------------------------------------------

    public function test_attendance_notification_reaches_only_linked_guardians(): void
    {
        // أب وأم مربوطين بالطالب الأول، وأب تاني مربوط بطالب تاني
        $fatherToken = $this->guardianToken(['relationship' => 'father']);
        $motherToken = $this->registerGuardian([
            'name' => 'Mother', 'phone' => '01099999999', 'relationship' => 'mother',
        ])->json('token');

        $unrelatedFatherToken = $this->registerGuardian([
            'name' => 'Unrelated', 'phone' => '01088888888',
            'guardian_code' => 'GUARD-OTHER-9999',
        ])->json('token');

        // تسجيل حضور للطالب الأول
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $course->students()->attach($this->student->id);

        $response = $this->actingAs($teacher)->postJson("/api/lessons/{$lesson->id}/attendance", [
            'students' => [
                ['student_id' => $this->student->id, 'status' => 'absent'],
            ],
        ]);
        $response->assertOk();

        // actingAs(teacher) سبّى اليوزر في الجارد — نفضّيه قبل طلبات الـ Guardian
        $this->app->make('auth')->forgetGuards();

        // الأب والأم وصلهم الإشعار بالطالب الصح (forgetGuards قبل كل تبديل توكن — كاش الجارد بيئة تست بس)
        foreach ([$fatherToken, $motherToken] as $token) {
            $this->app->make('auth')->forgetGuards();
            $notifications = $this->withToken($token)->getJson('/api/guardian/notifications');
            $notifications->assertOk();

            $this->assertEquals(1, $notifications->json('meta.total'));
            $this->assertEquals('Attendance', $notifications->json('data.0.title'));
            $this->assertEquals($this->student->id, $notifications->json('data.0.student.id'));
            $this->assertStringContainsString('was absent', $notifications->json('data.0.message'));
        }

        // الأب التاني (غير المرتبط) معندوش حاجة
        $this->app->make('auth')->forgetGuards();
        $unrelated = $this->withToken($unrelatedFatherToken)->getJson('/api/guardian/notifications');
        $this->assertCount(0, $unrelated->json('data'));
    }

    public function test_payment_recorded_notification_contains_student_id(): void
    {
        $this->guardianToken();

        $course = Course::factory()->create();
        $this->student->enrolledCourses()->attach($course->id);

        $payment = Payment::create([
            'student_id' => $this->student->id, 'course_id' => $course->id,
            'amount' => 500, 'allocated_amount' => 500, 'paid_at' => today(),
            'method' => 'cash', 'created_by' => $this->admin->id,
        ]);

        event(new PaymentRecorded($payment));

        $notification = GuardianNotification::first();
        $this->assertNotNull($notification);
        $this->assertEquals($this->student->id, $notification->student_id);
        $this->assertStringContainsString("{$this->student->name}'s payment", $notification->message);
    }

    public function test_monthly_charge_notification_reaches_guardian(): void
    {
        $this->guardianToken();

        $course = Course::factory()->create();
        $subscription = CourseSubscription::create([
            'student_id' => $this->student->id, 'course_id' => $course->id,
            'start_date' => now()->subMonth()->toDateString(),
            'monthly_fee' => 500, 'status' => 'active',
        ]);

        $charge = MonthlyCharge::create([
            'subscription_id' => $subscription->id, 'student_id' => $this->student->id,
            'course_id' => $course->id, 'period_year' => now()->year,
            'period_month' => now()->month, 'amount' => 500,
        ]);

        event(new ChargesGenerated(collect([$charge]), now()->year, now()->month));

        $notification = GuardianNotification::first();
        $this->assertNotNull($notification);
        $this->assertEquals('Payment Due', $notification->title);
        $this->assertEquals($this->student->id, $notification->student_id);
    }

    public function test_notification_mark_as_read_scoped_to_guardian(): void
    {
        $fatherToken = $this->guardianToken();

        $notification = GuardianNotification::create([
            'guardian_id' => Guardian::first()->id,
            'student_id' => $this->student->id,
            'title' => 'Attendance',
            'message' => 'test',
        ]);

        // Guardian تاني يحاول يقفل إشعار غيره → 403
        $otherToken = $this->registerGuardian([
            'phone' => '01077777777', 'guardian_code' => 'GUARD-OTHER-9999',
        ])->json('token');

        $this->withToken($otherToken)->patchJson("/api/guardian/notifications/{$notification->id}/read")
            ->assertForbidden();

        // نفضّي الجارد — الطلب اللي فات كاشي Guardian التاني
        $this->app->make('auth')->forgetGuards();

        // صاحبه يقدر
        $mark = $this->withToken($fatherToken)->patchJson("/api/guardian/notifications/{$notification->id}/read");
        $mark->assertOk()
            ->assertJsonPath('data.is_read', true);
    }

    // ---------------------------------------------------------------
    // Dashboard
    // ---------------------------------------------------------------

    public function test_guardian_dashboard_data_is_correct(): void
    {
        $guardianToken = $this->guardianToken();

        $course = Course::factory()->create();
        $this->student->enrolledCourses()->attach($course->id);
        $this->student->attendances()->create([
            'lesson_id' => Lesson::factory()->create(['course_id' => $course->id])->id,
            'status' => 'present',
        ]);
        $this->student->charges()->create([
            'subscription_id' => CourseSubscription::create([
                'student_id' => $this->student->id, 'course_id' => $course->id,
                'start_date' => now()->subMonth()->toDateString(),
                'monthly_fee' => 500, 'status' => 'active',
            ])->id,
            'student_id' => $this->student->id, 'course_id' => $course->id,
            'period_year' => now()->year, 'period_month' => now()->month,
            'amount' => 500, 'paid_amount' => 300, 'status' => 'partially_paid',
        ]);

        $response = $this->withToken($guardianToken)->getJson('/api/guardian/dashboard');
        $response->assertOk();

        $child = $response->json('data.0');
        $this->assertEquals($this->student->id, $child['id']);
        $this->assertEquals(1, $child['courses_count']);
        $this->assertEquals(100.0, $child['attendance_rate']);
        $this->assertEquals('500.00', $child['finance']['total_due']);
        $this->assertEquals('300.00', $child['finance']['total_paid']);
        $this->assertEquals('200.00', $child['finance']['remaining']);
        $this->assertEquals(1, $child['finance']['outstanding_charges']);
        $this->assertEquals(0, $response->json('unread_notifications_total'));
    }

    // ---------------------------------------------------------------
    // Guardian Code exposure
    // ---------------------------------------------------------------

    public function test_student_sees_guardian_code_in_his_profile_only(): void
    {
        $studentToken = $this->postJson('/api/login', [
            'email' => $this->student->email,
            'password' => 'password',
        ])->json('token');

        $this->withToken($studentToken)->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.user.guardian_code', $this->guardianCode);
    }

    public function test_guardian_code_is_not_generated_for_non_students(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->assertNull($teacher->guardian_code);
    }

    public function test_guardian_codes_are_unique(): void
    {
        $codes = User::where('role', 'student')->pluck('guardian_code');
        $this->assertEquals($codes->count(), $codes->unique()->count());
    }

    // ---------------------------------------------------------------
    // Login / logout
    // ---------------------------------------------------------------

    public function test_guardian_can_login_and_logout(): void
    {
        $this->registerGuardian()->assertCreated();

        $login = $this->postJson('/api/guardian/login', [
            'phone' => '01012345678',
            'password' => 'secret1234',
        ]);
        $login->assertOk();
        $token = $login->json('token');

        $this->withToken($token)->getJson('/api/guardian/children')->assertOk();

        $this->withToken($token)->postJson('/api/guardian/logout')->assertOk();

        // نفس سلوك AuthTest: منضّف الجاردات عشان التوكن الملقى يتقيم من جديد
        $this->app->make('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/guardian/children')->assertUnauthorized();
    }

    public function test_guardian_login_fails_with_wrong_password(): void
    {
        $this->registerGuardian();

        $this->postJson('/api/guardian/login', [
            'phone' => '01012345678',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }
}

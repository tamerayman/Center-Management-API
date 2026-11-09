<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // الـ limiter العام (api): 120/دقيقة لكل مستخدم
    // ---------------------------------------------------------------

    public function test_requests_within_limit_succeed(): void
    {
        $user = User::factory()->student()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->getJson('/api/courses')->assertOk();
        }
    }

    public function test_exceeding_api_limit_returns_429_with_retry_after(): void
    {
        $user = User::factory()->student()->create();

        $lastResponse = null;

        // الـ limit 120/دقيقة — 121 طلب: آخر واحد لازم يترفض
        for ($i = 0; $i < 121; $i++) {
            $lastResponse = $this->actingAs($user)->getJson('/api/courses?per_page=1');
        }

        $lastResponse->assertTooManyRequests();
        $this->assertNotNull($lastResponse->headers->get('Retry-After'));
    }

    public function test_rate_limit_is_per_user_not_global(): void
    {
        // مستخدم استهلك حصته
        $userA = User::factory()->student()->create();
        for ($i = 0; $i < 121; $i++) {
            $this->actingAs($userA)->getJson('/api/courses?per_page=1');
        }
        $this->actingAs($userA)->getJson('/api/courses?per_page=1')->assertTooManyRequests();

        // مستخدم تاني — حصته مستقلة تماماً
        $userB = User::factory()->student()->create();
        $this->actingAs($userB)->getJson('/api/courses')->assertOk();
    }

    public function test_normal_frontend_usage_does_not_hit_the_limit(): void
    {
        // محاكاة استخدام طبيعي: مجموعة endpoints متنوعة — كلها تنجح
        $user = User::factory()->student()->create();
        $course = Course::factory()->create();
        $course->students()->attach($user->id);

        $endpoints = [
            '/api/dashboard',
            '/api/my-courses',
            '/api/my-lessons',
            '/api/progress',
            '/api/notifications',
            '/api/my-attendance',
            '/api/my-results',
            '/api/profile',
        ];

        // جولتين على الـ 8 = 16 طلب — بعيد جداً عن 120
        foreach (range(1, 2) as $round) {
            foreach ($endpoints as $url) {
                $this->actingAs($user)->getJson($url)->assertOk();
            }
        }
    }

    // ---------------------------------------------------------------
    // مسارات الحساسة: auth (5/min) + guardian-auth (5/min بالموبايل)
    // ---------------------------------------------------------------

    public function test_guardian_login_is_rate_limited_by_phone(): void
    {
        // 5 محاولات فشل بموبايل معين
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/guardian/login', [
                'phone' => '01000000000',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        // المحاولة السادسة → 429 حتى لو غيّر الباسورد
        $this->postJson('/api/guardian/login', [
            'phone' => '01000000000',
            'password' => 'secret1234',
        ])->assertTooManyRequests();

        // موبايل مختلف → حصته مستقلة
        $this->postJson('/api/guardian/login', [
            'phone' => '01111111111',
            'password' => 'whatever',
        ])->assertUnauthorized();
    }

    public function test_guardian_registration_is_rate_limited(): void
    {
        // كود طالب حقيقي — عشان الرفض يبقى من الـ limiter مش من validation
        $student = User::factory()->student()->create();
        $code = $student->guardian_code;

        // الـ limiter keyed بالموبايل — حماية التخمين على نفس الهوية
        // 5 محاولات على نفس الموبايل (كلها بتفشل: الإيميل المستخدم بعد التسجيل الأول)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/guardian/register', [
                'name' => "G{$i}",
                'phone' => '01000000000',
                'password' => 'secret1234',
                'guardian_code' => $code,
                'relationship' => 'father',
            ]);
        }

        // السادسة بنفس الموبايل → 429
        $this->postJson('/api/guardian/register', [
            'name' => 'G6',
            'phone' => '01000000000',
            'password' => 'secret1234',
            'guardian_code' => $code,
            'relationship' => 'father',
        ])->assertTooManyRequests();
    }
}

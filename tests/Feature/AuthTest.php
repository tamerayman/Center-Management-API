<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_gets_token_profile_and_welcome_notification(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ahmed Ali',
            'email' => 'ahmed@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'role' => 'student',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'ahmed@example.com')
            ->assertJsonStructure(['message', 'user', 'token']);

        $user = User::whereEmail('ahmed@example.com')->first();

        $this->assertNotNull($user);
        $this->assertDatabaseHas(Profile::class, ['user_id' => $user->id]);
        $this->assertDatabaseHas(Notification::class, ['user_id' => $user->id, 'title' => 'Welcome']);
    }

    public function test_register_rejects_admin_role_and_invalid_data(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Hacker',
            'email' => 'hacker@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'role' => 'admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->postJson('/api/register', [
            'name' => 'No Email',
            'email' => 'not-an-email',
            'role' => 'student',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->student()->create(['password' => 'secret1234']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret1234',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_login_is_rate_limited_to_five_attempts_per_minute(): void
    {
        $user = User::factory()->create(['password' => 'secret1234']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_authenticated_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::factory()->create();

        $login = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $token = $login->json('token');

        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        // في feature tests نفس AuthManager بيفضل حي بين الـ requests ويكاشي اليوزر،
        // فمنضّف الجاردات عشان الطلب الجاي يتأكد من التوكن من جديد زي الإنتاج بالظبط
        $this->app->make('auth')->forgetGuards();

        // نفس التوكن بعد الـ logout مبقاش صالح
        $this->withToken($token)
            ->getJson('/api/my-courses')
            ->assertUnauthorized();
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}

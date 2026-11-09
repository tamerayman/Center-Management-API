<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'Reset password link sent to your email.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_response_is_same_for_unknown_email(): void
    {
        // رد متطابق عشان محدش يقدر يكتشف الإيميلات المسجلة
        $this->postJson('/api/forgot-password', ['email' => 'ghost@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Reset password link sent to your email.');
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'old-password']);

        $this->postJson('/api/forgot-password', ['email' => $user->email]);

        $token = null;

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use (&$token) {
                $token = $notification->token;

                return true;
            }
        );

        $this->assertNotNull($token);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertOk()
            ->assertJsonPath('message', 'Password has been reset successfully. You can now login.');

        // الدخول بكلمة السر الجديدة ينجح والقديمة يفشل
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'new-secret-123',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'old-password',
        ])->assertUnauthorized();
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertUnprocessable()
            ->assertJsonPath('message', __('passwords.token'));
    }
}

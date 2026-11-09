<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_can_request_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->student()->unverified()->create();

        $this->actingAs($user)->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Verification email sent.');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_requesting_again_gets_already_verified(): void
    {
        $user = User::factory()->create(); // verified by default

        $this->actingAs($user)->postJson('/api/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Email is already verified.');
    }

    public function test_user_can_verify_email_via_signed_link(): void
    {
        $user = User::factory()->student()->unverified()->create();

        $this->assertFalse($user->hasVerifiedEmail());

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_fails_with_wrong_hash(): void
    {
        $user = User::factory()->student()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('wrong@example.com')]
        );

        $this->getJson($verificationUrl)
            ->assertForbidden()
            ->assertJsonPath('message', 'Invalid verification link.');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_fails_without_valid_signature(): void
    {
        $user = User::factory()->student()->unverified()->create();

        $this->getJson("/api/email/verify/{$user->id}/".sha1($user->email))
            ->assertForbidden();
    }
}

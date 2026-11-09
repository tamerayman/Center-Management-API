<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_profile_with_statistics(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user)->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['user', 'profile']]);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/profile', [
            'phone' => '+201000000000',
            'bio' => 'Backend developer.',
            'city' => 'Cairo',
        ])->assertOk()
            ->assertJsonPath('data.profile.phone', '+201000000000');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'phone' => '+201000000000',
            'city' => 'Cairo',
        ]);
    }

    public function test_user_can_list_notifications_paginated(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 12) as $i) {
            Notification::create([
                'user_id' => $user->id,
                'title' => "Note {$i}",
                'message' => "Message {$i}",
            ]);
        }

        $this->actingAs($user)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(10, 'data');
    }

    public function test_user_can_filter_unread_notifications(): void
    {
        $user = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'title' => 'Read one', 'message' => 'm', 'is_read' => true]);
        Notification::create(['user_id' => $user->id, 'title' => 'Unread one', 'message' => 'm']);

        $response = $this->actingAs($user)->getJson('/api/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertEquals('Unread one', $response->json('data.0.title'));
    }

    public function test_user_cannot_mark_another_users_notification(): void
    {
        $user = User::factory()->create();
        $otherNotification = Notification::create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Not mine',
            'message' => 'm',
        ]);

        $this->actingAs($user)->patchJson("/api/notifications/{$otherNotification->id}/read")
            ->assertForbidden();
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'Mine',
            'message' => 'm',
        ]);

        $this->actingAs($user)->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            Notification::create(['user_id' => $user->id, 'title' => "N{$i}", 'message' => 'm']);
        }

        $this->actingAs($user)->patchJson('/api/notifications/read-all')
            ->assertOk();

        $this->assertEquals(0, $user->notifications()->where('is_read', false)->count());
    }
}

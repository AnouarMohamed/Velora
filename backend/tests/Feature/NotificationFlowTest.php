<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class NotificationFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_notification_index_returns_page_metadata_and_unread_count(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PARTICIPANT]);

        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'first',
            'title' => 'Première notification',
            'message' => 'Message visible.',
        ]);
        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'read',
            'title' => 'Notification lue',
            'message' => 'Message lu.',
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 30)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_notification_index_can_filter_to_unread_notifications(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_PARTICIPANT]);

        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'unread',
            'title' => 'Notification non lue',
            'message' => 'Message non lu.',
        ]);
        AppNotification::create([
            'user_id' => $user->id,
            'type' => 'read',
            'title' => 'Notification lue',
            'message' => 'Message lu.',
            'read_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications?unread_only=1')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'unread');
    }
}

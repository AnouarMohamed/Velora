<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class EventRequestReviewFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_admin_approves_event_request_and_creates_draft_event(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $eventRequest = $this->eventRequest([
            'preferred_start' => Carbon::now()->addDays(20),
            'preferred_end' => Carbon::now()->addDays(20)->addHours(6),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/event-requests/{$eventRequest->id}/review", [
            'decision' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('event_request.status', 'approved')
            ->assertJsonPath('event.status', 'draft')
            ->assertJsonPath('event.created_by', $admin->id)
            ->assertJsonPath('event.event_request_id', $eventRequest->id);

        $reviewedRequest = $eventRequest->fresh();
        $event = Event::query()->firstOrFail();

        $this->assertSame('approved', $reviewedRequest->status);
        $this->assertSame($admin->id, $reviewedRequest->reviewed_by_id);
        $this->assertNull($reviewedRequest->rejection_reason);
        $this->assertSame($eventRequest->id, $event->event_request_id);
        $this->assertSame(100, (int) $event->capacity);
        $this->assertSame(0, (int) $event->registered_count);
    }

    public function test_admin_rejects_event_request_without_creating_event(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $eventRequest = $this->eventRequest();

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/event-requests/{$eventRequest->id}/review", [
            'decision' => 'rejected',
            'rejection_reason' => 'Budget unavailable.',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('rejection_reason', 'Budget unavailable.')
            ->assertJsonPath('reviewed_by_id', $admin->id);

        $this->assertSame(0, Event::query()->count());
        $this->assertSame('rejected', $eventRequest->fresh()->status);
    }

    public function test_reviewed_event_request_cannot_be_reviewed_again(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $eventRequest = $this->eventRequest(['status' => 'approved']);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/event-requests/{$eventRequest->id}/review", [
            'decision' => 'rejected',
            'rejection_reason' => 'Changed decision.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cette demande a déjà été traitée.');

        $this->assertSame(0, Event::query()->count());
        $this->assertSame('approved', $eventRequest->fresh()->status);
    }

    /** @param array<string, mixed> $overrides */
    private function eventRequest(array $overrides = []): EventRequest
    {
        return EventRequest::create(array_merge([
            'user_id' => User::factory()->create(['role' => User::ROLE_CLIENT])->id,
            'title' => 'Client Security Workshop',
            'description' => 'Private workshop request.',
            'preferred_start' => Carbon::now()->addDays(14),
            'preferred_end' => Carbon::now()->addDays(14)->addHours(3),
            'location' => 'Rabat',
            'ticket_price' => 15,
            'contact_name' => 'Client User',
            'contact_email' => 'client@example.com',
            'contact_phone' => null,
            'status' => 'pending',
        ], $overrides));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class EventRequestClientFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_client_submits_event_request_with_default_contact_fields_and_base64_image(): void
    {
        Storage::fake('public');

        $admin = $this->user(User::ROLE_ADMIN);
        $client = $this->user(User::ROLE_CLIENT, [
            'name' => 'Client Owner',
            'email' => 'client-owner@example.com',
        ]);

        Sanctum::actingAs($client);

        $response = $this->postJson('/api/event-requests', $this->requestPayload([
            'contact_name' => '',
            'image_data' => base64_encode('small-image-payload'),
            'image_mime' => 'image/png',
        ]))
            ->assertCreated()
            ->assertJsonPath('contact_email', 'client-owner@example.com')
            ->assertJsonPath('contact_name', 'Client Owner')
            ->assertJsonPath('status', EventRequest::STATUS_PENDING);

        $eventRequest = EventRequest::query()->firstOrFail();

        $this->assertSame($eventRequest->id, $response->json('id'));
        $this->assertSame($client->id, $eventRequest->user_id);
        $this->assertNotNull($eventRequest->image_path);
        Storage::disk('public')->assertExists($eventRequest->image_path);

        $this->assertTrue(AppNotification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'admin_event_request_pending')
            ->exists());
    }

    public function test_client_with_pending_request_is_blocked_from_submitting_another(): void
    {
        $client = $this->user(User::ROLE_CLIENT, ['email' => 'blocked@example.com']);
        $this->eventRequestFor($client, ['status' => EventRequest::STATUS_PENDING]);

        Sanctum::actingAs($client);

        $this->postJson('/api/event-requests', $this->requestPayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Vous avez déjà une demande en attente. Supprimez-la pour en envoyer une nouvelle.')
            ->assertJsonPath('block_reason', 'pending');

        $this->assertSame(1, EventRequest::query()->count());
    }

    public function test_client_with_active_event_is_blocked_from_submitting_another_request(): void
    {
        $client = $this->user(User::ROLE_CLIENT, ['email' => 'active-event@example.com']);
        $approvedRequest = $this->eventRequestFor($client, ['status' => EventRequest::STATUS_APPROVED]);
        $this->eventForRequest($approvedRequest, [
            'status' => Event::STATUS_PUBLISHED,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHours(3),
        ]);

        Sanctum::actingAs($client);

        $this->postJson('/api/event-requests', $this->requestPayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Votre événement est encore en cours. Attendez sa fin pour envoyer une nouvelle demande.')
            ->assertJsonPath('block_reason', 'active_event');

        $this->assertSame(1, EventRequest::query()->count());
    }

    public function test_client_can_delete_own_pending_request_and_image(): void
    {
        Storage::fake('public');

        $client = $this->user(User::ROLE_CLIENT, ['email' => 'delete-me@example.com']);
        Storage::disk('public')->put('event-requests/delete-me.png', 'image-bytes');
        $eventRequest = $this->eventRequestFor($client, [
            'image_path' => 'event-requests/delete-me.png',
            'status' => EventRequest::STATUS_PENDING,
        ]);

        Sanctum::actingAs($client);

        $this->deleteJson("/api/event-requests/{$eventRequest->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Demande supprimée.');

        $this->assertSame(0, EventRequest::query()->count());
        Storage::disk('public')->assertMissing('event-requests/delete-me.png');
    }

    public function test_client_cannot_delete_another_clients_request_or_reviewed_request(): void
    {
        $owner = $this->user(User::ROLE_CLIENT, ['email' => 'owner@example.com']);
        $other = $this->user(User::ROLE_CLIENT, ['email' => 'other@example.com']);
        $ownedRequest = $this->eventRequestFor($owner);
        $reviewedRequest = $this->eventRequestFor($other, ['status' => EventRequest::STATUS_APPROVED]);

        Sanctum::actingAs($other);

        $this->deleteJson("/api/event-requests/{$ownedRequest->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Demande introuvable.');

        $this->deleteJson("/api/event-requests/{$reviewedRequest->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Seules les demandes en attente peuvent être supprimées.');

        $this->assertSame(2, EventRequest::query()->count());
    }

    /** @param array<string, mixed> $overrides */
    private function user(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function requestPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Client Security Workshop',
            'description' => 'Private workshop request.',
            'preferred_start' => Carbon::now()->addDays(14)->toIso8601String(),
            'preferred_end' => Carbon::now()->addDays(14)->addHours(3)->toIso8601String(),
            'location' => 'Rabat',
            'ticket_price' => 15,
            'contact_name' => 'Client Contact',
            'contact_phone' => '+212600000000',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function eventRequestFor(User $client, array $overrides = []): EventRequest
    {
        return EventRequest::create(array_merge([
            'user_id' => $client->id,
            'title' => 'Existing Client Request',
            'description' => 'Existing request.',
            'preferred_start' => Carbon::now()->addDays(10),
            'preferred_end' => Carbon::now()->addDays(10)->addHours(4),
            'location' => 'Casablanca',
            'ticket_price' => 10,
            'contact_name' => $client->name,
            'contact_email' => $client->email,
            'contact_phone' => null,
            'status' => EventRequest::STATUS_PENDING,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function eventForRequest(EventRequest $eventRequest, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'event_request_id' => $eventRequest->id,
            'organizer_id' => null,
            'created_by' => null,
            'title' => $eventRequest->title,
            'description' => $eventRequest->description,
            'location' => $eventRequest->location,
            'start_at' => Carbon::now()->addDays(5),
            'end_at' => Carbon::now()->addDays(5)->addHours(4),
            'capacity' => 100,
            'registered_count' => 0,
            'ticket_price' => $eventRequest->ticket_price,
            'status' => Event::STATUS_DRAFT,
        ], $overrides));
    }
}

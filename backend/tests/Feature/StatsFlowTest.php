<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class StatsFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_admin_stats_include_role_counts_pending_work_and_past_event_ticket_counts(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->user(User::ROLE_CLIENT);
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $eventRequest = $this->eventRequest([
            'status' => EventRequest::STATUS_APPROVED,
            'contact_name' => 'Client Owner',
            'contact_email' => 'client@example.test',
        ]);
        $this->eventRequest(['status' => EventRequest::STATUS_PENDING]);
        $pastEvent = $this->event($eventRequest, [
            'organizer_id' => $organizer->id,
            'status' => Event::STATUS_PUBLISHED,
            'start_at' => Carbon::now()->subDays(5),
            'end_at' => Carbon::now()->subDays(5)->addHours(2),
            'registered_count' => 1,
        ]);
        $this->event(null, ['status' => Event::STATUS_PENDING_PUBLICATION]);
        $this->paidRegistration($participant, $pastEvent, '30.00');

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('users_total', 4)
            ->assertJsonPath('users_by_role.admin', 1)
            ->assertJsonPath('users_by_role.client', 1)
            ->assertJsonPath('users_by_role.organizer', 1)
            ->assertJsonPath('users_by_role.participant', 1)
            ->assertJsonPath('pending_requests', 1)
            ->assertJsonPath('pending_publications', 1)
            ->assertJsonPath('registrations_total', 1)
            ->assertJsonPath('revenue', 30)
            ->assertJsonPath('past_events.0.id', $pastEvent->id)
            ->assertJsonPath('past_events.0.tickets_count', 1)
            ->assertJsonPath('past_events.0.event_request.contact_email', 'client@example.test');
    }

    public function test_admin_stats_cache_is_invalidated_after_tracked_model_changes(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('users_total', 1);

        $this->user(User::ROLE_CLIENT);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('users_total', 2)
            ->assertJsonPath('users_by_role.client', 1);
    }

    public function test_client_stats_group_requests_and_sum_revenue_for_owned_events(): void
    {
        $client = $this->user(User::ROLE_CLIENT, [
            'name' => 'Client Owner',
            'email' => 'owner@example.test',
        ]);
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $pendingRequest = $this->eventRequestForClient($client, [
            'title' => 'Pending Workshop',
            'status' => EventRequest::STATUS_PENDING,
        ]);
        $approvedFutureRequest = $this->eventRequestForClient($client, [
            'title' => 'Future Workshop',
            'status' => EventRequest::STATUS_APPROVED,
        ]);
        $approvedPastRequest = $this->eventRequestForClient($client, [
            'title' => 'Past Workshop',
            'status' => EventRequest::STATUS_APPROVED,
        ]);
        $this->eventRequestForClient($client, [
            'title' => 'Rejected Workshop',
            'status' => EventRequest::STATUS_REJECTED,
        ]);
        $futureEvent = $this->event($approvedFutureRequest, [
            'organizer_id' => $organizer->id,
            'status' => Event::STATUS_PUBLISHED,
            'start_at' => Carbon::now()->addDays(4),
            'end_at' => Carbon::now()->addDays(4)->addHours(2),
        ]);
        $pastEvent = $this->event($approvedPastRequest, [
            'organizer_id' => $organizer->id,
            'status' => Event::STATUS_PUBLISHED,
            'start_at' => Carbon::now()->subDays(4),
            'end_at' => Carbon::now()->subDays(4)->addHours(2),
        ]);
        $this->paidRegistration($participant, $futureEvent, '20.00');
        $this->paidRegistration($participant, $pastEvent, '15.50');

        Sanctum::actingAs($client);

        $this->getJson('/api/client/stats')
            ->assertOk()
            ->assertJsonPath('total_revenue', 35.5)
            ->assertJsonPath('can_submit_new_request', false)
            ->assertJsonPath('block_reason', 'pending')
            ->assertJsonPath('requests.pending.0.id', $pendingRequest->id)
            ->assertJsonCount(1, 'requests.pending')
            ->assertJsonCount(2, 'requests.approved')
            ->assertJsonCount(1, 'requests.rejected')
            ->assertJsonPath('featured_events.0.id', $futureEvent->id)
            ->assertJsonPath('featured_events.0.tickets_count', 1)
            ->assertJsonPath('past_events.0.id', $pastEvent->id)
            ->assertJsonPath('past_events.0.tickets_count', 1);
    }

    /** @param array<string, mixed> $overrides */
    private function user(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function eventRequestForClient(User $client, array $overrides = []): EventRequest
    {
        return $this->eventRequest(array_merge([
            'user_id' => $client->id,
            'contact_name' => $client->getAttribute('name'),
            'contact_email' => $client->getAttribute('email'),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function eventRequest(array $overrides = []): EventRequest
    {
        return EventRequest::create(array_merge([
            'title' => 'Client Security Workshop',
            'description' => 'Private security training.',
            'preferred_start' => Carbon::now()->addDays(20),
            'preferred_end' => Carbon::now()->addDays(20)->addHours(3),
            'location' => 'Casablanca',
            'ticket_price' => '25.00',
            'contact_name' => 'Client',
            'contact_email' => 'client-'.uniqid().'@example.test',
            'contact_phone' => '+212600000000',
            'status' => EventRequest::STATUS_PENDING,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function event(?EventRequest $eventRequest, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'event_request_id' => $eventRequest?->id,
            'organizer_id' => null,
            'created_by' => null,
            'title' => $eventRequest?->getAttribute('title') ?? 'Security Summit',
            'description' => $eventRequest?->getAttribute('description') ?? 'Operational event.',
            'location' => $eventRequest?->getAttribute('location') ?? 'Rabat',
            'room' => 'Main Hall',
            'start_at' => Carbon::now()->addDays(10),
            'end_at' => Carbon::now()->addDays(10)->addHours(3),
            'capacity' => 100,
            'registered_count' => 0,
            'ticket_price' => $eventRequest?->getAttribute('ticket_price') ?? '20.00',
            'status' => Event::STATUS_PUBLISHED,
        ], $overrides));
    }

    private function paidRegistration(User $participant, Event $event, string $amount): Registration
    {
        $registration = Registration::create([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'ticket_type' => 'standard',
            'status' => 'registered',
            'payment_status' => 'paid',
            'ticket_code' => 'ticket-'.$event->id.'-'.$participant->id,
            'amount' => $amount,
            'paid_at' => now(),
            'registered_at' => now(),
        ]);

        Payment::create([
            'registration_id' => $registration->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'status' => 'completed',
            'method' => 'card_mock',
        ]);

        return $registration;
    }
}

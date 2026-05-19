<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class StaffRegistrationFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_organizer_filters_registrations_by_mongo_event_id(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $otherOrganizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $event = $this->eventFor($organizer);
        $otherEvent = $this->eventFor($otherOrganizer);

        $registration = $this->registration(User::factory()->create(['role' => User::ROLE_PARTICIPANT]), $event, [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->registration(User::factory()->create(['role' => User::ROLE_PARTICIPANT]), $otherEvent);

        Sanctum::actingAs($organizer);

        $this->getJson("/api/organizer/registrations?event_id={$event->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $registration->id)
            ->assertJsonPath('data.0.event_id', $event->id)
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.paid', 1)
            ->assertJsonPath('summary.pending', 0);
    }

    public function test_organizer_cannot_filter_registrations_for_unowned_event(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $otherOrganizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $otherEvent = $this->eventFor($otherOrganizer);
        $this->eventFor($organizer);

        Sanctum::actingAs($organizer);

        $this->getJson("/api/organizer/registrations?event_id={$otherEvent->id}")
            ->assertForbidden();
    }

    public function test_staff_registration_filter_rejects_non_object_id_event_id(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $this->eventFor($organizer);

        Sanctum::actingAs($organizer);

        $this->getJson('/api/organizer/registrations?event_id=123')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('event_id');
    }

    public function test_admin_registration_events_use_mongo_aggregation_counts(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $event = $this->eventFor($organizer, ['registered_count' => 3]);

        $this->registration(User::factory()->create(['role' => User::ROLE_PARTICIPANT]), $event, [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->registration(User::factory()->create(['role' => User::ROLE_PARTICIPANT]), $event, [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->registration(User::factory()->create(['role' => User::ROLE_PARTICIPANT]), $event);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/registrations/events')
            ->assertOk();

        $row = collect($response->json())->firstWhere('id', $event->id);

        $this->assertNotNull($row);
        $this->assertSame(3, $row['registrations_count']);
        $this->assertSame(2, $row['paid_registrations_count']);
    }

    public function test_organizer_deletes_unpaid_registration_and_decrements_count(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $event = $this->eventFor($organizer, ['registered_count' => 1]);
        $registration = $this->registration(User::factory()->create(['role' => User::ROLE_PARTICIPANT]), $event);

        Sanctum::actingAs($organizer);

        $this->deleteJson("/api/organizer/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Inscription supprimée.');

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(0, (int) $event->fresh()->registered_count);
    }

    public function test_staff_cannot_delete_paid_or_unowned_registration(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $otherOrganizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $event = $this->eventFor($organizer, ['registered_count' => 1]);
        $otherEvent = $this->eventFor($otherOrganizer, ['registered_count' => 1]);
        $paidRegistration = $this->registration(User::factory()->create(['role' => User::ROLE_PARTICIPANT]), $event, [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        $otherRegistration = $this->registration(User::factory()->create(['role' => User::ROLE_PARTICIPANT]), $otherEvent);

        Sanctum::actingAs($organizer);

        $this->deleteJson("/api/organizer/registrations/{$paidRegistration->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Impossible de supprimer une inscription déjà payée.');

        $this->deleteJson("/api/organizer/registrations/{$otherRegistration->id}")
            ->assertForbidden();

        $this->assertSame(2, Registration::query()->count());
        $this->assertSame(1, (int) $event->fresh()->registered_count);
        $this->assertSame(1, (int) $otherEvent->fresh()->registered_count);
    }

    /** @param array<string, mixed> $overrides */
    private function eventFor(User $user, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'organizer_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Staff Managed Event',
            'description' => 'A managed event description.',
            'location' => 'Casablanca',
            'room' => 'A2',
            'start_at' => Carbon::now()->addDays(15),
            'end_at' => Carbon::now()->addDays(15)->addHours(4),
            'capacity' => 100,
            'registered_count' => 0,
            'ticket_price' => 20,
            'status' => Event::STATUS_PUBLISHED,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function registration(User $participant, Event $event, array $overrides = []): Registration
    {
        return Registration::create(array_merge([
            'event_id' => $event->id,
            'user_id' => $participant->id,
            'status' => 'registered',
            'payment_status' => 'pending',
            'ticket_code' => 'ticket-'.$participant->id,
            'amount' => $event->ticket_price,
            'registered_at' => now(),
        ], $overrides));
    }
}

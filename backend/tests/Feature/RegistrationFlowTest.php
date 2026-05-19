<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_participant_registers_for_paid_event(): void
    {
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent(['ticket_price' => 25, 'capacity' => 2]);

        Sanctum::actingAs($participant);

        $this->postJson("/api/events/{$event->id}/register")
            ->assertCreated()
            ->assertJsonPath('event_id', $event->id)
            ->assertJsonPath('user_id', $participant->id)
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('status', 'registered');

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(1, (int) $event->fresh()->registered_count);
    }

    public function test_free_event_registration_is_paid_immediately(): void
    {
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent(['ticket_price' => 0]);

        Sanctum::actingAs($participant);

        $this->postJson("/api/events/{$event->id}/register")
            ->assertCreated()
            ->assertJsonPath('payment_status', 'paid');

        $registration = Registration::query()->firstOrFail();
        $payment = Payment::query()->firstOrFail();

        $this->assertNotNull($registration->paid_at);
        $this->assertSame($registration->id, $payment->registration_id);
        $this->assertSame('completed', $payment->status);
        $this->assertSame('free', $payment->method);
    }

    public function test_duplicate_registration_is_rejected_without_incrementing_capacity(): void
    {
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent(['capacity' => 5]);

        Sanctum::actingAs($participant);

        $this->postJson("/api/events/{$event->id}/register")->assertCreated();

        $this->postJson("/api/events/{$event->id}/register")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Déjà inscrit.');

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, (int) $event->fresh()->registered_count);
    }

    public function test_full_event_registration_is_rejected(): void
    {
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent([
            'capacity' => 1,
            'registered_count' => 1,
        ]);

        Sanctum::actingAs($participant);

        $this->postJson("/api/events/{$event->id}/register")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Événement complet.');

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(1, (int) $event->fresh()->registered_count);
    }

    public function test_participant_pays_pending_registration(): void
    {
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent(['ticket_price' => 45]);
        $registration = $this->registration($participant, $event, [
            'amount' => 45,
            'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($participant);

        $this->postJson("/api/registrations/{$registration->id}/pay")
            ->assertOk()
            ->assertJsonPath('id', $registration->id)
            ->assertJsonPath('payment_status', 'paid');

        $payment = Payment::query()->firstOrFail();

        $this->assertSame('paid', $registration->fresh()->payment_status);
        $this->assertNotNull($registration->fresh()->paid_at);
        $this->assertSame($registration->id, $payment->registration_id);
        $this->assertSame('completed', $payment->status);
        $this->assertSame('card_mock', $payment->method);
    }

    public function test_unpaid_registration_can_be_cancelled_and_decrements_capacity(): void
    {
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent(['registered_count' => 1]);
        $registration = $this->registration($participant, $event, [
            'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($participant);

        $this->deleteJson("/api/registrations/{$registration->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Inscription annulée.');

        $this->assertSame(0, Registration::query()->count());
        $this->assertSame(0, (int) $event->fresh()->registered_count);
    }

    public function test_paid_registration_cannot_be_cancelled(): void
    {
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent(['registered_count' => 1]);
        $registration = $this->registration($participant, $event, [
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($participant);

        $this->deleteJson("/api/registrations/{$registration->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Impossible d\'annuler une inscription déjà payée.');

        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, (int) $event->fresh()->registered_count);
    }

    public function test_participant_cannot_manage_another_participants_registration(): void
    {
        $owner = $this->user(User::ROLE_PARTICIPANT);
        $other = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent(['registered_count' => 1]);
        $registration = $this->registration($owner, $event);

        Sanctum::actingAs($other);

        $this->postJson("/api/registrations/{$registration->id}/pay")
            ->assertForbidden();

        $this->deleteJson("/api/registrations/{$registration->id}")
            ->assertForbidden();

        $this->getJson("/api/registrations/{$registration->id}/ticket")
            ->assertForbidden();

        $this->assertSame('pending', $registration->fresh()->payment_status);
        $this->assertSame(1, Registration::query()->count());
        $this->assertSame(1, (int) $event->fresh()->registered_count);
    }

    public function test_ticket_download_requires_paid_registration_and_returns_ticket_payload(): void
    {
        $participant = $this->user(User::ROLE_PARTICIPANT);
        $event = $this->publishedEvent([
            'title' => 'Ticketed Security Summit',
            'location' => 'Marrakech',
        ]);
        $registration = $this->registration($participant, $event, [
            'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($participant);

        $this->getJson("/api/registrations/{$registration->id}/ticket")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Paiement requis pour le billet.');

        $registration->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->get("/api/registrations/{$registration->id}/ticket")
            ->assertOk()
            ->assertHeader('content-type', 'application/json');

        $payload = json_decode($response->streamedContent(), true);

        $this->assertSame($registration->ticket_code, $payload['ticket']);
        $this->assertSame('Ticketed Security Summit', $payload['event']);
        $this->assertSame($participant->name, $payload['participant']);
        $this->assertSame('Marrakech', $payload['location']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** @param array<string, mixed> $overrides */
    private function publishedEvent(array $overrides = []): Event
    {
        return Event::create(array_merge([
            'title' => 'Security Summit',
            'description' => 'A practical conference for security teams.',
            'location' => 'Casablanca',
            'room' => 'Main Hall',
            'start_at' => Carbon::now()->addDays(10),
            'end_at' => Carbon::now()->addDays(10)->addHours(4),
            'capacity' => 100,
            'registered_count' => 0,
            'ticket_price' => 20,
            'status' => 'published',
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

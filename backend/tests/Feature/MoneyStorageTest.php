<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use MongoDB\BSON\ObjectId;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class MoneyStorageTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_event_prices_are_stored_as_cents_and_exposed_as_decimal_fields(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        Sanctum::actingAs($organizer);

        $response = $this->postJson('/api/organizer/events', [
            'title' => 'Payment Architecture Summit',
            'description' => 'A focused event.',
            'location' => 'Casablanca',
            'room' => 'A1',
            'start_at' => Carbon::now()->addDays(12)->toIso8601String(),
            'end_at' => Carbon::now()->addDays(12)->addHours(3)->toIso8601String(),
            'capacity' => 120,
            'ticket_price' => '12.345',
        ])->assertCreated();

        $eventId = $response->json('id');
        $rawEvent = $this->rawDocument('events', $eventId);

        $response->assertJsonPath('ticket_price', '12.35');
        $this->assertSame(1235, (int) $rawEvent['ticket_price_cents']);
        $this->assertArrayNotHasKey('ticket_price', $rawEvent);
        $this->assertSame('12.35', Event::query()->findOrFail($eventId)->ticket_price);
    }

    public function test_event_request_prices_are_stored_as_cents(): void
    {
        $eventRequest = EventRequest::create([
            'title' => 'Private Security Workshop',
            'description' => 'Internal workshop.',
            'preferred_start' => Carbon::now()->addMonth(),
            'preferred_end' => Carbon::now()->addMonth()->addHours(4),
            'location' => 'Rabat',
            'ticket_price' => '7.50',
            'contact_name' => 'Client',
            'contact_email' => 'client@example.test',
            'contact_phone' => '+212600000000',
            'status' => 'pending',
        ]);

        $rawRequest = $this->rawDocument('event_requests', $eventRequest->id);

        $this->assertSame(750, (int) $rawRequest['ticket_price_cents']);
        $this->assertArrayNotHasKey('ticket_price', $rawRequest);
        $this->assertSame('7.50', $eventRequest->fresh()->ticket_price);
    }

    public function test_registration_and_payment_amounts_are_stored_as_cents(): void
    {
        $participant = User::factory()->create(['role' => User::ROLE_PARTICIPANT]);
        $event = Event::create([
            'title' => 'Security Summit',
            'description' => 'A practical conference for security teams.',
            'location' => 'Casablanca',
            'room' => 'Main Hall',
            'start_at' => Carbon::now()->addDays(10),
            'end_at' => Carbon::now()->addDays(10)->addHours(4),
            'capacity' => 100,
            'registered_count' => 0,
            'ticket_price' => '45.67',
            'status' => Event::STATUS_PUBLISHED,
        ]);

        Sanctum::actingAs($participant);

        $registrationId = $this->postJson("/api/events/{$event->id}/register")
            ->assertCreated()
            ->assertJsonPath('amount', '45.67')
            ->json('id');

        $rawRegistration = $this->rawDocument('registrations', $registrationId);
        $this->assertSame(4567, (int) $rawRegistration['amount_cents']);
        $this->assertArrayNotHasKey('amount', $rawRegistration);

        $this->postJson("/api/registrations/{$registrationId}/pay")
            ->assertOk()
            ->assertJsonPath('amount', '45.67')
            ->assertJsonPath('payment_status', 'paid');

        $payment = Payment::query()->firstOrFail();
        $rawPayment = $this->rawDocument('payments', $payment->id);

        $this->assertSame(4567, (int) $rawPayment['amount_cents']);
        $this->assertArrayNotHasKey('amount', $rawPayment);
        $this->assertSame('45.67', Registration::query()->findOrFail($registrationId)->amount);
        $this->assertSame('45.67', $payment->fresh()->amount);
    }

    public function test_admin_stats_sum_completed_payment_cents(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Payment::create([
            'registration_id' => 'registration-1',
            'amount' => '10.01',
            'currency' => 'EUR',
            'status' => 'completed',
            'method' => 'card_mock',
        ]);

        Payment::create([
            'registration_id' => 'registration-2',
            'amount' => '2.99',
            'currency' => 'EUR',
            'status' => 'completed',
            'method' => 'card_mock',
        ]);

        Payment::create([
            'registration_id' => 'registration-3',
            'amount' => '100.00',
            'currency' => 'EUR',
            'status' => 'failed',
            'method' => 'card_mock',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('revenue', 13);
    }

    /** @return array<string, mixed> */
    private function rawDocument(string $collection, string $id): array
    {
        $document = DB::connection('mongodb')
            ->getDatabase()
            ->selectCollection($collection)
            ->findOne(['_id' => new ObjectId($id)]);

        $this->assertNotNull($document);

        return (array) $document;
    }
}

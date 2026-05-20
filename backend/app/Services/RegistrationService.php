<?php

namespace App\Services;

use App\Exceptions\RegistrationException;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service managing the lifecycle of participant registrations for events.
 *
 * This service handles registration creation, payment processing, and cancellations.
 * It ensures data integrity through database transactions and addresses concurrency
 * issues related to event capacity limits.
 */
class RegistrationService
{
    /**
     * Registers a participant for an event.
     *
     * @param  User  $participant  The user who wants to register.
     * @param  Event  $event  The event being registered for.
     * @return Registration The newly created registration.
     *
     * @throws RegistrationException If the event is closed, full, or the user is already registered.
     */
    public function register(User $participant, Event $event): Registration
    {
        // Only published events allow new registrations.
        if ($event->status !== Event::STATUS_PUBLISHED) {
            throw new RegistrationException('Événement non ouvert aux inscriptions.');
        }

        return DB::transaction(function () use ($participant, $event) {
            // Lock the event record to get the most accurate registered_count and prevent overbooking.
            $freshEvent = Event::query()->whereKey($event->id)->firstOrFail();

            if ((int) $freshEvent->registered_count >= (int) $freshEvent->capacity) {
                throw new RegistrationException('Événement complet.');
            }

            // Check if the user is already registered to avoid duplicates.
            $existing = Registration::query()
                ->where('event_id', $freshEvent->id)
                ->where('user_id', $participant->id)
                ->first();

            if ($existing) {
                throw new RegistrationException('Déjà inscrit.', registration: $existing);
            }

            // Atomically increment the registered count while double-checking the capacity.
            // This is an extra layer of protection against race conditions.
            $incremented = Event::query()
                ->whereKey($freshEvent->id)
                ->where('registered_count', '<', (int) $freshEvent->capacity)
                ->increment('registered_count');

            if (! $incremented) {
                throw new RegistrationException('Événement complet.');
            }

            $amountCents = Money::toCents($freshEvent->ticket_price);
            $isFree = $amountCents <= 0;

            // Create the registration record.
            $registration = Registration::create([
                'event_id' => $freshEvent->id,
                'user_id' => $participant->id,
                'status' => 'registered',
                'payment_status' => $isFree ? 'paid' : 'pending',
                'ticket_code' => (string) Str::uuid(), // Unique code for ticket verification.
                'amount' => $freshEvent->ticket_price,
                'paid_at' => $isFree ? now() : null,
                'registered_at' => now(),
            ]);

            // If the event is free, we create a 'completed' payment record immediately.
            if ($isFree) {
                Payment::create([
                    'registration_id' => $registration->id,
                    'amount' => 0,
                    'currency' => 'EUR',
                    'status' => 'completed',
                    'method' => 'free',
                    'meta' => ['note' => 'Gratuit'],
                ]);
            }

            $registration->load('event', 'user');
            // Notify admins and organizers about the new participant.
            NotificationService::participantRegistered($registration);

            return $registration;
        });
    }

    /**
     * Processes a payment for a pending registration.
     *
     * @throws RegistrationException If the registration is already paid.
     */
    public function pay(Registration $registration): Registration
    {
        if ($registration->payment_status === 'paid') {
            throw new RegistrationException('Déjà payé.', 200, $registration);
        }

        return DB::transaction(function () use ($registration) {
            $amount = $registration->amount;

            // Atomically update payment status to prevent double-payment.
            $updated = Registration::query()
                ->whereKey($registration->id)
                ->where('payment_status', 'pending')
                ->update([
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ]);

            if (! $updated) {
                $registration->refresh();
                throw new RegistrationException('Déjà payé.', 200, $registration);
            }

            // Create a payment record to track the transaction.
            Payment::create([
                'registration_id' => $registration->id,
                'amount' => $amount,
                'currency' => 'EUR',
                'status' => 'completed',
                'method' => 'card_mock', // Mock payment method for simulation.
                'meta' => ['simulated' => true],
            ]);

            $registration->refresh();
            $registration->load([
                'event',
                'event.eventRequest',
                'user',
            ]);

            // Notify admins and organizers about the successful payment.
            NotificationService::participantPaid($registration);

            return $registration;
        });
    }

    /**
     * Cancels a pending registration.
     * Only unpaid registrations can be cancelled via this method.
     *
     * @throws RegistrationException If the registration is already paid.
     */
    public function cancel(Registration $registration): void
    {
        if ($registration->payment_status === 'paid') {
            throw new RegistrationException('Impossible d\'annuler une inscription déjà payée.');
        }

        DB::transaction(function () use ($registration) {
            // Delete the registration record if it's still unpaid.
            $deleted = Registration::query()
                ->whereKey($registration->id)
                ->where('payment_status', 'pending')
                ->delete();

            if (! $deleted) {
                throw new RegistrationException('Impossible d\'annuler une inscription déjà payée.');
            }

            // Free up a slot in the event's capacity.
            Event::query()
                ->whereKey($registration->event_id)
                ->where('registered_count', '>', 0)
                ->decrement('registered_count');
        });
    }
}

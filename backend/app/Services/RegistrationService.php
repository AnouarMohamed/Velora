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
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Service managing the lifecycle of participant registrations for events.
 *
 * This service handles registration creation, payment processing, and cancellations.
 * The important rules live here because they must stay identical no matter which
 * controller or staff workflow triggers them:
 * - published events only;
 * - no overbooking;
 * - one registration per participant per event;
 * - paid registrations cannot be cancelled;
 * - money is persisted through the model as integer cents.
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
        // Participants can only register for events that are already visible and live.
        if ($event->status !== Event::STATUS_PUBLISHED) {
            throw new RegistrationException('Événement non ouvert aux inscriptions.');
        }

        try {
            return DB::transaction(function () use ($participant, $event) {
                // Reload the event inside the transaction so capacity checks use the latest document state.
                $freshEvent = Event::query()->whereKey($event->id)->firstOrFail();

                if ((int) $freshEvent->registered_count >= (int) $freshEvent->capacity) {
                    throw new RegistrationException('Événement complet.');
                }

                // Friendly pre-check for duplicates; the Mongo unique index remains the final guarantee.
                $existing = Registration::query()
                    ->where('event_id', $freshEvent->id)
                    ->where('user_id', $participant->id)
                    ->first();

                if ($existing) {
                    throw new RegistrationException('Déjà inscrit.', registration: $existing);
                }

                // The conditional increment is the overbooking guard under concurrent registrations.
                // If another request fills the event first, this update affects zero documents.
                $incremented = Event::query()
                    ->whereKey($freshEvent->id)
                    ->where('registered_count', '<', (int) $freshEvent->capacity)
                    ->increment('registered_count');

                if (! $incremented) {
                    throw new RegistrationException('Événement complet.');
                }

                $amountCents = Money::toCents($freshEvent->ticket_price);
                $isFree = $amountCents <= 0;

                // The model accepts the API-compatible decimal amount and persists amount_cents.
                $registration = Registration::create([
                    'event_id' => $freshEvent->id,
                    'user_id' => $participant->id,
                    'status' => 'registered',
                    'payment_status' => $isFree ? 'paid' : 'pending',
                    'ticket_code' => $this->uniqueTicketCode(),
                    'amount' => $freshEvent->ticket_price,
                    'paid_at' => $isFree ? now() : null,
                    'registered_at' => now(),
                ]);

                // Free events still get a completed payment record so stats and ticket rules stay uniform.
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
                // Staff dashboards are notification-driven, so a successful registration fans out here.
                NotificationService::participantRegistered($registration);

                return $registration;
            });
        } catch (BulkWriteException $exception) {
            // Convert a Mongo duplicate-key race into the same domain error as the pre-check.
            $this->throwDuplicateRegistrationIfNeeded($exception, $participant, $event);

            throw $exception;
        }
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

            // Only a pending registration can move to paid, which prevents duplicate payment records.
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

            // This is a mock payment ledger entry, but it follows the same cents-based money storage.
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

            // Payment completion can unblock tickets and operational reporting.
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
            // Delete only if the document is still pending; a simultaneous payment wins over cancellation.
            $deleted = Registration::query()
                ->whereKey($registration->id)
                ->where('payment_status', 'pending')
                ->delete();

            if (! $deleted) {
                throw new RegistrationException('Impossible d\'annuler une inscription déjà payée.');
            }

            // Keep the denormalized event counter consistent with the removed registration.
            Event::query()
                ->whereKey($registration->event_id)
                ->where('registered_count', '>', 0)
                ->decrement('registered_count');
        });
    }

    private function uniqueTicketCode(): string
    {
        // UUID collisions are extremely unlikely, but the unique index makes them possible to detect.
        // A few retries keep the API response clean without hiding a persistent storage problem.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $ticketCode = (string) Str::uuid();

            if (! Registration::query()->where('ticket_code', $ticketCode)->exists()) {
                return $ticketCode;
            }
        }

        throw new RegistrationException('Impossible de générer un billet unique.');
    }

    /**
     * Handles the race where two requests pass the duplicate pre-check before one wins the unique index.
     *
     * @throws RegistrationException
     */
    private function throwDuplicateRegistrationIfNeeded(BulkWriteException $exception, User $participant, Event $event): void
    {
        if (! $this->isDuplicateKey($exception) || ! $this->isRegistrationUniquenessConflict($exception)) {
            return;
        }

        $existing = Registration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $participant->id)
            ->first();

        if (! $existing) {
            return;
        }

        throw new RegistrationException('Déjà inscrit.', registration: $existing);
    }

    private function isDuplicateKey(BulkWriteException $exception): bool
    {
        return str_contains($exception->getMessage(), 'duplicate key')
            || str_contains($exception->getMessage(), 'E11000');
    }

    private function isRegistrationUniquenessConflict(BulkWriteException $exception): bool
    {
        return str_contains($exception->getMessage(), 'registrations_event_user_unique');
    }
}

<?php

namespace App\Services;

use App\Exceptions\RegistrationException;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function register(User $participant, Event $event): Registration
    {
        if ($event->status !== 'published') {
            throw new RegistrationException('Événement non ouvert aux inscriptions.');
        }

        return DB::transaction(function () use ($participant, $event) {
            $freshEvent = Event::query()->whereKey($event->id)->firstOrFail();

            if ((int) $freshEvent->registered_count >= (int) $freshEvent->capacity) {
                throw new RegistrationException('Événement complet.');
            }

            $existing = Registration::query()
                ->where('event_id', $freshEvent->id)
                ->where('user_id', $participant->id)
                ->first();

            if ($existing) {
                throw new RegistrationException('Déjà inscrit.', registration: $existing);
            }

            $incremented = Event::query()
                ->whereKey($freshEvent->id)
                ->where('registered_count', '<', (int) $freshEvent->capacity)
                ->increment('registered_count');

            if (! $incremented) {
                throw new RegistrationException('Événement complet.');
            }

            $amount = (float) $freshEvent->ticket_price;
            $isFree = $amount <= 0;

            $registration = Registration::create([
                'event_id' => $freshEvent->id,
                'user_id' => $participant->id,
                'status' => 'registered',
                'payment_status' => $isFree ? 'paid' : 'pending',
                'ticket_code' => (string) Str::uuid(),
                'amount' => $freshEvent->ticket_price,
                'paid_at' => $isFree ? now() : null,
                'registered_at' => now(),
            ]);

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
            NotificationService::participantRegistered($registration);

            return $registration;
        });
    }

    public function pay(Registration $registration): Registration
    {
        if ($registration->payment_status === 'paid') {
            throw new RegistrationException('Déjà payé.', 200, $registration);
        }

        return DB::transaction(function () use ($registration) {
            $amount = (float) $registration->amount;

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

            Payment::create([
                'registration_id' => $registration->id,
                'amount' => $amount,
                'currency' => 'EUR',
                'status' => 'completed',
                'method' => 'card_mock',
                'meta' => ['simulated' => true],
            ]);

            $registration->refresh();
            $registration->load([
                'event',
                'event.eventRequest',
                'user',
            ]);

            NotificationService::participantPaid($registration);

            return $registration;
        });
    }

    public function cancel(Registration $registration): void
    {
        if ($registration->payment_status === 'paid') {
            throw new RegistrationException('Impossible d\'annuler une inscription déjà payée.');
        }

        DB::transaction(function () use ($registration) {
            $deleted = Registration::query()
                ->whereKey($registration->id)
                ->where('payment_status', 'pending')
                ->delete();

            if (! $deleted) {
                throw new RegistrationException('Impossible d\'annuler une inscription déjà payée.');
            }

            Event::query()
                ->whereKey($registration->event_id)
                ->where('registered_count', '>', 0)
                ->decrement('registered_count');
        });
    }
}

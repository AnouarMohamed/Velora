<?php

namespace App\Services\Registrations;

use App\Exceptions\RegistrationException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ParticipantRegistrationService
{
    public function __construct(private readonly RegistrationService $registrations) {}

    public function register(User $participant, Event $event): Registration
    {
        $this->ensureParticipant($participant);

        return $this->registrations->register($participant, $event);
    }

    public function pay(User $participant, Registration $registration): Registration
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        return $this->registrations->pay($registration);
    }

    public function cancel(User $participant, Registration $registration): void
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        $this->registrations->cancel($registration);
    }

    public function registrationForEvent(User $participant, Event $event): ?Registration
    {
        $this->ensureParticipant($participant);

        return Registration::query()
            ->where('user_id', $participant->getKey())
            ->where('event_id', $event->getKey())
            ->with($this->registrationEventWith())
            ->first();
    }

    public function listForParticipant(User $participant, ?string $paymentStatus): LengthAwarePaginator
    {
        $this->ensureParticipant($participant);

        $query = Registration::query()
            ->where('user_id', $participant->getKey())
            ->with($this->registrationEventWith())
            ->orderBy('created_at', 'desc');

        if (in_array($paymentStatus, ['paid', 'pending'], true)) {
            $query->where('payment_status', $paymentStatus);
        }

        return $query->paginate(20);
    }

    public function ticketFor(User $participant, Registration $registration): RegistrationTicket
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        if ($registration->getAttribute('payment_status') !== 'paid') {
            throw new RegistrationException('Paiement requis pour le billet.');
        }

        $registration->load('event', 'user');
        $event = $registration->event;
        $user = $registration->user;

        return new RegistrationTicket(
            'billet-'.$registration->getKey().'.json',
            [
                'ticket' => $registration->getAttribute('ticket_code'),
                'event' => $event?->getAttribute('title'),
                'participant' => $user?->getAttribute('name'),
                'starts_at' => $event?->getAttribute('start_at')?->toIso8601String(),
                'location' => $event?->getAttribute('location'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function registrationEventWith(): array
    {
        return [
            'event' => fn ($q) => $q->select($this->registrationEventSelect()),
            'event.eventRequest' => fn ($q) => $q->select('id', 'image_path'),
        ];
    }

    /** @return list<string> */
    private function registrationEventSelect(): array
    {
        return [
            'id',
            'event_request_id',
            'title',
            'description',
            'start_at',
            'end_at',
            'location',
            'room',
            'ticket_price_cents',
            'status',
            'image_path',
        ];
    }

    private function ensureParticipantOwnsRegistration(User $participant, Registration $registration): void
    {
        $this->ensureParticipant($participant);

        if ((string) $registration->getAttribute('user_id') !== (string) $participant->getKey()) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }

    private function ensureParticipant(User $user): void
    {
        if ($user->getAttribute('role') !== User::ROLE_PARTICIPANT) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }
}

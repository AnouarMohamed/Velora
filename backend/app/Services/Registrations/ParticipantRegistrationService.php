<?php

namespace App\Services\Registrations;

use App\Exceptions\RegistrationException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Service handling registration operations from the participant's perspective.
 *
 * It provides methods for participants to register for events, pay, cancel, and retrieve their tickets.
 * Access is strictly restricted to users with the ROLE_PARTICIPANT role.
 */
class ParticipantRegistrationService
{
    /**
     * @param  RegistrationService  $registrations  The core registration logic service.
     */
    public function __construct(private readonly RegistrationService $registrations) {}

    /**
     * Registers a participant for an event.
     *
     * @param  User  $participant  The user registering (must be a participant).
     * @param  Event  $event  The event to register for.
     * @return Registration The newly created registration.
     *
     * @throws RegistrationException If the user is not a participant or registration fails.
     */
    public function register(User $participant, Event $event): Registration
    {
        $this->ensureParticipant($participant);

        return $this->registrations->register($participant, $event);
    }

    /**
     * Processes payment for a registration.
     *
     * @param  User  $participant  The participant paying for their registration.
     * @param  Registration  $registration  The registration to pay for.
     * @return Registration The updated registration with 'paid' status.
     *
     * @throws RegistrationException If the participant doesn't own the registration.
     */
    public function pay(User $participant, Registration $registration): Registration
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        return $this->registrations->pay($registration);
    }

    /**
     * Cancels a participant's registration.
     *
     * @param  User  $participant  The participant cancelling.
     * @param  Registration  $registration  The registration to cancel.
     *
     * @throws RegistrationException If the participant doesn't own the registration.
     */
    public function cancel(User $participant, Registration $registration): void
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        $this->registrations->cancel($registration);
    }

    /**
     * Retrieves a participant's registration for a specific event.
     *
     * Useful for checking if a user is already registered for an event on the event details page.
     */
    public function registrationForEvent(User $participant, Event $event): ?Registration
    {
        $this->ensureParticipant($participant);

        return Registration::query()
            ->where('user_id', $participant->getKey())
            ->where('event_id', $event->getKey())
            ->with($this->registrationEventWith())
            ->first();
    }

    /**
     * Lists all registrations for a participant, optionally filtered by payment status.
     *
     * @param  string|null  $paymentStatus  'paid' or 'pending'.
     */
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

    /**
     * Generates a ticket for a paid registration.
     *
     *
     * @throws RegistrationException If the registration is not paid.
     */
    public function ticketFor(User $participant, Registration $registration): RegistrationTicket
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        // Tickets are only available for confirmed (paid) registrations.
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

    /**
     * Returns the relationships to eager load for a registration.
     *
     * @return array<string, mixed>
     */
    private function registrationEventWith(): array
    {
        return [
            'event' => fn ($q) => $q->select($this->registrationEventSelect()),
            'event.eventRequest' => fn ($q) => $q->select('id', 'image_path'),
        ];
    }

    /**
     * Returns the fields to select from the event table.
     *
     * @return list<string>
     */
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

    /**
     * Enforces ownership: only the participant who registered can manage the registration.
     *
     * @throws RegistrationException
     */
    private function ensureParticipantOwnsRegistration(User $participant, Registration $registration): void
    {
        $this->ensureParticipant($participant);

        if ((string) $registration->getAttribute('user_id') !== (string) $participant->getKey()) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }

    /**
     * Enforces the participant role.
     *
     * @throws RegistrationException
     */
    private function ensureParticipant(User $user): void
    {
        if ($user->getAttribute('role') !== User::ROLE_PARTICIPANT) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }
}

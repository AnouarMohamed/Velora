<?php

namespace App\Services\Registrations;

use App\Exceptions\RegistrationException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Service gérant les opérations d'inscription du point de vue du participant.
 *
 * Il fournit des méthodes permettant aux participants de s'inscrire à des événements, de payer, d'annuler et de récupérer leurs billets.
 * L'accès est strictement réservé aux utilisateurs ayant le rôle ROLE_PARTICIPANT.
 */
class ParticipantRegistrationService
{
    /**
     * @param  RegistrationService  $registrations  Le service de logique d'inscription de base.
     */
    public function __construct(private readonly RegistrationService $registrations) {}

    /**
     * Inscrit un participant à un événement.
     *
     * @param  User  $participant  L'utilisateur qui s'inscrit (doit être un participant).
     * @param  Event  $event  L'événement auquel s'inscrire.
     * @return Registration La nouvelle inscription créée.
     *
     * @throws RegistrationException Si l'utilisateur n'est pas un participant ou si l'inscription échoue.
     */
    public function register(User $participant, Event $event): Registration
    {
        $this->ensureParticipant($participant);

        return $this->registrations->register($participant, $event);
    }

    /**
     * Traite le paiement d'une inscription.
     *
     * @param  User  $participant  Le participant payant son inscription.
     * @param  Registration  $registration  L'inscription à payer.
     * @return Registration L'inscription mise à jour avec le statut 'paid'.
     *
     * @throws RegistrationException Si le participant n'est pas le propriétaire de l'inscription.
     */
    public function pay(User $participant, Registration $registration): Registration
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        return $this->registrations->pay($registration);
    }

    /**
     * Annule l'inscription d'un participant.
     *
     * @param  User  $participant  Le participant qui annule.
     * @param  Registration  $registration  L'inscription à annuler.
     *
     * @throws RegistrationException Si le participant n'est pas le propriétaire de l'inscription.
     */
    public function cancel(User $participant, Registration $registration): void
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        $this->registrations->cancel($registration);
    }

    /**
     * Récupère l'inscription d'un participant pour un événement spécifique.
     *
     * Utile pour vérifier si un utilisateur est déjà inscrit à un événement sur la page des détails de l'événement.
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
     * Liste toutes les inscriptions d'un participant, éventuellement filtrées par statut de paiement.
     *
     * @param  string|null  $paymentStatus  'paid' ou 'pending'.
     * @return LengthAwarePaginator<int, Registration>
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
     * Génère un billet pour une inscription payée.
     *
     *
     * @throws RegistrationException Si l'inscription n'est pas payée.
     */
    public function ticketFor(User $participant, Registration $registration): RegistrationTicket
    {
        $this->ensureParticipantOwnsRegistration($participant, $registration);

        // Les billets ne sont disponibles que pour les inscriptions confirmées (payées).
        if ($registration->getAttribute('payment_status') !== 'paid') {
            throw new RegistrationException('Paiement requis pour le billet.');
        }

        $registration->load('event', 'user');
        $event = $registration->event;
        $user = $registration->user;

        return new RegistrationTicket(
            'billet-'.$this->stringValue($registration->getKey()).'.json',
            [
                'ticket' => $registration->getAttribute('ticket_code'),
                'event' => $event?->getAttribute('title'),
                'participant' => $user?->getAttribute('name'),
                'starts_at' => $this->isoDate($event?->getAttribute('start_at')),
                'location' => $event?->getAttribute('location'),
            ],
        );
    }

    /**
     * Retourne les relations à charger avec impatience pour une inscription.
     *
     * @return list<string>
     */
    private function registrationEventWith(): array
    {
        return [
            'event:'.implode(',', $this->registrationEventSelect()),
            'event.eventRequest:id,image_path',
        ];
    }

    /**
     * Retourne les champs à sélectionner dans la table des événements.
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
     * Impose la propriété : seul le participant qui s'est inscrit peut gérer l'inscription.
     *
     * @throws RegistrationException
     */
    private function ensureParticipantOwnsRegistration(User $participant, Registration $registration): void
    {
        $this->ensureParticipant($participant);

        if ($this->stringValue($registration->getAttribute('user_id')) !== $this->stringValue($participant->getKey())) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }

    /**
     * Impose le rôle de participant.
     *
     * @throws RegistrationException
     */
    private function ensureParticipant(User $user): void
    {
        if ($user->getAttribute('role') !== User::ROLE_PARTICIPANT) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }

    private function isoDate(mixed $value): ?string
    {
        return $value instanceof Carbon ? $value->toIso8601String() : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

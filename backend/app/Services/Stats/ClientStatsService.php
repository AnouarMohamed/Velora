<?php

namespace App\Services\Stats;

use App\Exceptions\StatsException;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Payment;
use App\Models\User;
use App\Services\EventRequests\EventRequestEligibilityService;
use App\Services\RegistrationStatsService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Service fournissant un tableau de bord personnalisé et des statistiques pour les Clients.
 *
 * Il agrège les données relatives aux propres demandes d'événements d'un client et aux revenus générés
 * par les événements organisés en son nom.
 */
class ClientStatsService
{
    /**
     * @param  RegistrationStatsService  $registrationStats  Service pour gérer le comptage des inscriptions.
     * @param  EventRequestEligibilityService  $eventRequestEligibility  Service pour vérifier si le client peut soumettre de nouvelles demandes.
     */
    public function __construct(
        private readonly RegistrationStatsService $registrationStats,
        private readonly EventRequestEligibilityService $eventRequestEligibility,
    ) {}

    /**
     * Génère la charge utile statistique pour un client spécifique.
     *
     * @param  User  $client  L'instance de l'utilisateur client.
     * @return array<string, mixed>
     *
     * @throws StatsException Si l'utilisateur n'est pas un client.
     */
    public function payloadFor(User $client): array
    {
        $this->ensureClient($client);

        $email = $this->stringValue($client->getAttribute('email'));
        $eventIds = $this->eventIdsFor($email);
        $requests = $this->requestsFor($email);
        $blockReason = $this->eventRequestEligibility->blockingReasonFor($client);

        return [
            'total_revenue' => $eventIds === [] ? 0.0 : $this->totalRevenue($eventIds),
            'featured_events' => $this->featuredEvents($email),
            'past_events' => $this->pastEvents($email),
            'can_submit_new_request' => $blockReason === null,
            'block_reason' => $blockReason,
            'requests' => $this->requestsByStatus($requests),
        ];
    }

    /**
     * Retourne les identifiants de tous les événements provenant des demandes de ce client.
     *
     * @return list<string>
     */
    private function eventIdsFor(string $email): array
    {
        $ids = Event::query()
            ->whereHas('eventRequest', fn ($query) => $query->where('contact_email', $email))
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_scalar($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        /** @var list<string> $ids */
        return $ids;
    }

    /**
     * Récupère toutes les demandes d'événements pour l'e-mail d'un client, y compris les données d'événements liées.
     *
     * @return EloquentCollection<int, EventRequest>
     */
    private function requestsFor(string $email): EloquentCollection
    {
        $requests = EventRequest::query()
            ->where('contact_email', $email)
            ->with('event:id,title,status,event_request_id,ticket_price_cents')
            ->orderBy('created_at', 'desc')
            ->get();

        $events = $requests
            ->pluck('event')
            ->filter(fn (mixed $event): bool => $event instanceof Event)
            ->values();

        $this->registrationStats->attachCount($events, 'registrations_count');

        return $requests;
    }

    /**
     * Calcule le revenu total généré par tous les événements appartenant à ce client.
     *
     * @param  list<string>  $eventIds
     */
    private function totalRevenue(array $eventIds): float
    {
        return Money::floatFromCents(Payment::query()
            ->whereHas('registration', fn ($query) => $query->whereIn('event_id', $eventIds))
            ->where('status', 'completed')
            ->sum('amount_cents'));
    }

    /**
     * Retourne les événements "Mis en avant" (actifs/à venir) pour le client.
     *
     * @return list<array<string, mixed>>
     */
    private function featuredEvents(string $email): array
    {
        $events = $this->clientEventsQuery($email)
            ->notFinished()
            ->orderBy('start_at', 'asc')
            ->get();

        $this->registrationStats->attachCount($events, 'tickets_count', 'paid');

        $formatted = $events
            ->map(fn (Event $event): array => $this->formatEvent($event))
            ->values()
            ->all();

        /** @var list<array<string, mixed>> $formatted */
        return $formatted;
    }

    /**
     * Retourne les événements passés (terminés) pour le client.
     *
     * @return list<array<string, mixed>>
     */
    private function pastEvents(string $email): array
    {
        $events = $this->clientEventsQuery($email)
            ->finished()
            ->orderBy('end_at', 'desc')
            ->orderBy('start_at', 'desc')
            ->get();

        $this->registrationStats->attachCount($events, 'tickets_count', 'paid');

        $formatted = $events
            ->map(fn (Event $event): array => $this->formatEvent($event))
            ->values()
            ->all();

        /** @var list<array<string, mixed>> $formatted */
        return $formatted;
    }

    /**
     * Requête scopée pour les événements liés aux demandes approuvées d'un client.
     *
     * @return Builder<Event>
     */
    private function clientEventsQuery(string $email): Builder
    {
        return Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->whereHas('eventRequest', function ($query) use ($email): void {
                $query->where('contact_email', $email)
                    ->where('status', EventRequest::STATUS_APPROVED);
            })
            ->with(['organizer:id,name', 'eventRequest']);
    }

    /**
     * Formate un modèle Event pour l'affichage du tableau de bord.
     *
     * @return array<string, mixed>
     */
    private function formatEvent(Event $event): array
    {
        return [
            'id' => $event->getKey(),
            'title' => $event->getAttribute('title'),
            'description' => $event->getAttribute('description'),
            'image_url' => $event->getAttribute('image_url'),
            'location' => $event->getAttribute('location'),
            'start_at' => $event->getAttribute('start_at'),
            'end_at' => $event->getAttribute('end_at'),
            'registered_count' => $event->getAttribute('registered_count'),
            'capacity' => $event->getAttribute('capacity'),
            'tickets_count' => $this->intValue($event->getAttribute('tickets_count')),
            'ticket_price' => $this->floatValue($event->getAttribute('ticket_price')),
            'organizer' => $event->relationLoaded('organizer') ? $event->getRelation('organizer') : null,
        ];
    }

    /**
     * Formate un modèle EventRequest, y compris les indicateurs d'inscription de son événement lié.
     *
     * @return array<string, mixed>
     */
    private function formatRequest(EventRequest $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $request->toArray();
        $event = $request->relationLoaded('event') ? $request->getRelation('event') : null;
        $data['registrations_count'] = $event instanceof Event
            ? $this->intValue($event->getAttribute('registrations_count'))
            : 0;

        return $data;
    }

    /**
     * Groupe les demandes d'événements par leur statut (en attente, approuvé, rejeté).
     *
     * @param  EloquentCollection<int, EventRequest>  $requests
     * @return array<string, list<array<string, mixed>>>
     */
    private function requestsByStatus(EloquentCollection $requests): array
    {
        return [
            EventRequest::STATUS_PENDING => $this->formatRequestsWithStatus($requests, EventRequest::STATUS_PENDING),
            EventRequest::STATUS_APPROVED => $this->formatRequestsWithStatus($requests, EventRequest::STATUS_APPROVED),
            EventRequest::STATUS_REJECTED => $this->formatRequestsWithStatus($requests, EventRequest::STATUS_REJECTED),
        ];
    }

    /**
     * Filtre et formate les demandes pour un statut spécifique.
     *
     * @param  EloquentCollection<int, EventRequest>  $requests
     * @return list<array<string, mixed>>
     */
    private function formatRequestsWithStatus(EloquentCollection $requests, string $status): array
    {
        $formatted = $requests
            ->where('status', $status)
            ->map(fn (EventRequest $request): array => $this->formatRequest($request))
            ->values()
            ->all();

        /** @var list<array<string, mixed>> $formatted */
        return $formatted;
    }

    /**
     * Impose un accès réservé aux clients.
     *
     * @throws StatsException
     */
    private function ensureClient(User $user): void
    {
        if ($user->getAttribute('role') !== User::ROLE_CLIENT) {
            throw new StatsException('Accès refusé pour ce rôle.');
        }
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}

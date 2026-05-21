<?php

namespace App\Services\Registrations;

use App\Exceptions\RegistrationException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationStatsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service pour la gestion administrative des inscriptions.
 *
 * Il permet aux Organisateurs et aux Administrateurs de visualiser, rechercher et supprimer des inscriptions pour les événements qu'ils gèrent.
 * Il fournit également des statistiques récapitulatives (payées vs en attente) pour les inscriptions.
 */
class StaffRegistrationService
{
    /**
     * @param  RegistrationStatsService  $registrationStats  Service pour calculer et attacher les nombres d'inscriptions.
     */
    public function __construct(private readonly RegistrationStatsService $registrationStats) {}

    /**
     * Récupère les événements gérés par un organisateur avec leurs nombres d'inscriptions.
     *
     * @return Collection<int, Event>
     */
    public function eventsForOrganizer(User $organizer): Collection
    {
        $this->ensureOrganizer($organizer);

        return $this->eventsWithCounts(
            $this->organizerEventsQuery($organizer)
                ->orderBy('start_at', 'asc')
                ->get($this->eventSelect()),
        );
    }

    /**
     * Récupère les événements gérés par un administrateur (ou liés aux organisateurs qu'il supervise) avec les comptes.
     *
     * @return Collection<int, Event>
     */
    public function eventsForAdmin(User $admin): Collection
    {
        $this->ensureAdmin($admin);

        return $this->eventsWithCounts(
            $this->adminEventsQuery($admin)
                ->orderBy('start_at', 'desc')
                ->get($this->eventSelect()),
        );
    }

    /**
     * Liste les inscriptions pour un organisateur avec filtrage et recherche.
     *
     * @param  array<string, mixed>  $filters  Clés possibles : 'event_id', 'payment_status', 'q' (recherche).
     * @return array<string, mixed> Retourne un tableau structuré avec les données, les méta-données (pagination) et un résumé.
     */
    public function listForOrganizer(User $organizer, array $filters): array
    {
        $this->ensureOrganizer($organizer);

        return $this->list($this->organizerEventsQuery($organizer), $filters);
    }

    /**
     * Liste les inscriptions pour un administrateur avec filtrage et recherche.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listForAdmin(User $admin, array $filters): array
    {
        $this->ensureAdmin($admin);

        return $this->list($this->adminEventsQuery($admin), $filters);
    }

    /**
     * Supprime une inscription en tant qu'organisateur.
     */
    public function deleteForOrganizer(User $organizer, Registration $registration): void
    {
        $this->ensureOrganizer($organizer);
        $this->delete($registration, $this->organizerEventsQuery($organizer));
    }

    /**
     * Supprime une inscription en tant qu'administrateur.
     */
    public function deleteForAdmin(User $admin, Registration $registration): void
    {
        $this->ensureAdmin($admin);
        $this->delete($registration, $this->adminEventsQuery($admin));
    }

    /**
     * Requête de base pour les événements auxquels un organisateur a accès.
     *
     * Règle d'accès : Événements créés par lui OU assignés à lui en tant qu'organisateur.
     *
     * @return Builder<Event>
     */
    private function organizerEventsQuery(User $user): Builder
    {
        return Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->where(function ($query) use ($user): void {
                $query->where('organizer_id', $user->getKey())
                    ->orWhere('created_by', $user->getKey());
            });
    }

    /**
     * Requête de base pour les événements auxquels un administrateur a accès.
     *
     * Règle d'accès : Événements créés par/assignés à lui, OU événements gérés par n'importe quel organisateur.
     *
     * @return Builder<Event>
     */
    private function adminEventsQuery(User $user): Builder
    {
        return Event::query()->where(function ($query) use ($user): void {
            $query->where('organizer_id', $user->getKey())
                ->orWhere('created_by', $user->getKey())
                ->orWhereHas('organizer', fn ($organizer) => $organizer->where('role', User::ROLE_ORGANIZER))
                ->orWhereHas('creator', fn ($creator) => $creator->where('role', User::ROLE_ORGANIZER));
        });
    }

    /**
     * Logique centrale pour lister et filtrer les inscriptions sur tous les événements accessibles.
     *
     * @param  Builder<Event>  $eventsQuery  Requête d'événement scopée basée sur le rôle.
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function list(Builder $eventsQuery, array $filters): array
    {
        $eventIds = $this->eventIds($eventsQuery);

        // Si l'utilisateur n'a aucun événement, retourner une structure vide immédiatement.
        if ($eventIds === []) {
            return $this->emptyListPayload();
        }

        // Vérification de sécurité : si un event_id spécifique est demandé, s'assurer qu'il fait partie des événements accessibles.
        $eventId = $this->optionalString($filters['event_id'] ?? null);
        if ($eventId !== null && ! in_array($eventId, $eventIds, true)) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }

        $registrationsQuery = Registration::query()
            ->whereIn('event_id', $eventIds)
            ->with([
                'event:id,event_request_id,title,description,start_at,end_at,location,room,status,image_path',
                'event.eventRequest:id,image_path',
                'user:id,name,email',
            ]);

        // Appliquer les filtres
        if ($eventId !== null) {
            $registrationsQuery->where('event_id', $eventId);
        }

        $paymentFilter = $filters['payment_status'] ?? 'all';
        if ($paymentFilter !== 'all') {
            $registrationsQuery->where('payment_status', $paymentFilter);
        }

        // Appliquer la recherche par mot-clé (nom, e-mail, titre, code du billet)
        $search = $this->optionalString($filters['q'] ?? null);
        if ($search !== null) {
            $this->applySearch($registrationsQuery, $search);
        }

        // Calculer le résumé avant la pagination
        $summary = $this->summary($eventIds, $eventId);
        $paginated = $registrationsQuery->orderBy('created_at', 'desc')->paginate(20);

        return [
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => $summary,
        ];
    }

    /**
     * Effectue une suppression définitive d'une inscription et met à jour les compteurs d'événements.
     *
     * @param  Builder<Event>  $eventsQuery  Requête de contrôle d'accès.
     */
    private function delete(Registration $registration, Builder $eventsQuery): void
    {
        $eventIds = $this->eventIds($eventsQuery);

        // Vérifier que l'inscription appartient à un événement géré par le membre du personnel.
        if (! in_array($this->stringValue($registration->getAttribute('event_id')), $eventIds, true)) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }

        // Règle métier : Les inscriptions payées ne peuvent pas être supprimées. Elles doivent être remboursées/gérées manuellement.
        if ($registration->getAttribute('payment_status') === 'paid') {
            throw new RegistrationException('Impossible de supprimer une inscription déjà payée.');
        }

        // Supprimer atomiquement et décrémenter le compteur d'inscriptions de l'événement.
        DB::transaction(function () use ($registration): void {
            $registration->delete();

            Event::query()
                ->whereKey($registration->getAttribute('event_id'))
                ->where('registered_count', '>', 0)
                ->decrement('registered_count');
        });
    }

    /**
     * Attache les nombres d'inscriptions à une collection d'événements.
     *
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Event>
     */
    private function eventsWithCounts(Collection $events): Collection
    {
        $this->registrationStats->attachCount($events, 'registrations_count');
        $this->registrationStats->attachCount($events, 'paid_registrations_count', 'paid');

        return $events;
    }

    /** @return list<string> Champs pour la sélection du résumé de l'événement. */
    private function eventSelect(): array
    {
        return ['id', 'title', 'start_at', 'status', 'registered_count', 'capacity'];
    }

    /**
     * @param  Builder<Event>  $eventsQuery
     * @return list<string>
     */
    private function eventIds(Builder $eventsQuery): array
    {
        $ids = (clone $eventsQuery)
            ->pluck('id')
            ->filter(fn (mixed $eventId): bool => is_scalar($eventId))
            ->map(fn (mixed $eventId): string => (string) $eventId)
            ->values()
            ->all();

        /** @var list<string> $ids */
        return $ids;
    }

    /** @return array<string, mixed> Structure vide pour les réponses de liste. */
    private function emptyListPayload(): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 0,
            ],
            'summary' => [
                'total' => 0,
                'paid' => 0,
                'pending' => 0,
            ],
        ];
    }

    /**
     * Calcule le résumé des inscriptions (total/payé/en attente) pour le périmètre donné.
     *
     * @param  list<string>  $eventIds  Événements accessibles.
     * @param  string|null  $eventId  Filtre d'événement spécifique.
     * @return array{total: int, paid: int, pending: int}
     */
    private function summary(array $eventIds, ?string $eventId): array
    {
        $summaryBase = Registration::query()->whereIn('event_id', $eventIds);

        if ($eventId !== null) {
            $summaryBase->where('event_id', $eventId);
        }

        return [
            'total' => (clone $summaryBase)->count(),
            'paid' => (clone $summaryBase)->where('payment_status', 'paid')->count(),
            'pending' => (clone $summaryBase)->where('payment_status', 'pending')->count(),
        ];
    }

    /**
     * Applique la recherche par mot-clé sur plusieurs entités liées.
     *
     * @param  Builder<Registration>  $query  Requête d'inscription.
     * @param  string  $search  Terme de recherche.
     */
    private function applySearch(Builder $query, string $search): void
    {
        $query->where(function ($registrationQuery) use ($search): void {
            $registrationQuery->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })->orWhereHas('event', function ($eventQuery) use ($search): void {
                $eventQuery->where('title', 'like', '%'.$search.'%');
            })->orWhere('ticket_code', 'like', '%'.$search.'%');
        });
    }

    /** @throws RegistrationException */
    private function ensureOrganizer(User $user): void
    {
        if ($user->getAttribute('role') !== User::ROLE_ORGANIZER) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }

    /** @throws RegistrationException */
    private function ensureAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

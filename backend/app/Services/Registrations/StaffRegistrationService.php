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
 * Service for administrative management of registrations.
 *
 * It allows Organizers and Admins to view, search, and delete registrations for the events they manage.
 * It also provides summary statistics (paid vs pending) for registrations.
 */
class StaffRegistrationService
{
    /**
     * @param  RegistrationStatsService  $registrationStats  Service to calculate and attach registration counts.
     */
    public function __construct(private readonly RegistrationStatsService $registrationStats) {}

    /**
     * Retrieves events managed by an organizer with their registration counts.
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
     * Retrieves events managed by an admin (or linked to organizers they supervise) with counts.
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
     * Lists registrations for an organizer with filtering and searching.
     *
     * @param  array<string, mixed>  $filters  Possible keys: 'event_id', 'payment_status', 'q' (search).
     * @return array<string, mixed> Returns a structured array with data, meta (pagination), and summary.
     */
    public function listForOrganizer(User $organizer, array $filters): array
    {
        $this->ensureOrganizer($organizer);

        return $this->list($this->organizerEventsQuery($organizer), $filters);
    }

    /**
     * Lists registrations for an admin with filtering and searching.
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
     * Deletes a registration as an organizer.
     */
    public function deleteForOrganizer(User $organizer, Registration $registration): void
    {
        $this->ensureOrganizer($organizer);
        $this->delete($registration, $this->organizerEventsQuery($organizer));
    }

    /**
     * Deletes a registration as an admin.
     */
    public function deleteForAdmin(User $admin, Registration $registration): void
    {
        $this->ensureAdmin($admin);
        $this->delete($registration, $this->adminEventsQuery($admin));
    }

    /**
     * Base query for events an organizer has access to.
     *
     * Access rule: Events created by them OR assigned to them as organizer.
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
     * Base query for events an admin has access to.
     *
     * Access rule: Events created by/assigned to them, OR events managed by any organizer.
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
     * Core logic for listing and filtering registrations across all accessible events.
     *
     * @param  Builder  $eventsQuery  Scoped event query based on role.
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function list(Builder $eventsQuery, array $filters): array
    {
        $eventIds = $this->eventIds($eventsQuery);

        // If the user has no events, return an empty structure immediately.
        if ($eventIds === []) {
            return $this->emptyListPayload();
        }

        // Security check: if a specific event_id is requested, ensure it's within accessible events.
        $eventId = isset($filters['event_id']) ? (string) $filters['event_id'] : null;
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

        // Apply filters
        if ($eventId !== null) {
            $registrationsQuery->where('event_id', $eventId);
        }

        $paymentFilter = $filters['payment_status'] ?? 'all';
        if ($paymentFilter !== 'all') {
            $registrationsQuery->where('payment_status', $paymentFilter);
        }

        // Apply keyword search (name, email, title, ticket code)
        if (! empty($filters['q'])) {
            $this->applySearch($registrationsQuery, (string) $filters['q']);
        }

        // Calculate summary before pagination
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
     * Performs a hard delete of a registration and updates event counters.
     *
     * @param  Builder  $eventsQuery  Access control query.
     */
    private function delete(Registration $registration, Builder $eventsQuery): void
    {
        $eventIds = $this->eventIds($eventsQuery);

        // Verify that the registration belongs to an event the staff member manages.
        if (! in_array((string) $registration->getAttribute('event_id'), $eventIds, true)) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }

        // Business rule: Paid registrations cannot be deleted. They must be refunded/handled manually.
        if ($registration->getAttribute('payment_status') === 'paid') {
            throw new RegistrationException('Impossible de supprimer une inscription déjà payée.');
        }

        // Atomically delete and decrement the event's registration counter.
        DB::transaction(function () use ($registration): void {
            $registration->delete();

            Event::query()
                ->whereKey($registration->getAttribute('event_id'))
                ->where('registered_count', '>', 0)
                ->decrement('registered_count');
        });
    }

    /**
     * Attaches registration counts to a collection of events.
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

    /** @return list<string> Fields for event summary selection. */
    private function eventSelect(): array
    {
        return ['id', 'title', 'start_at', 'status', 'registered_count', 'capacity'];
    }

    /** @return list<string> IDs of events in the query. */
    private function eventIds(Builder $eventsQuery): array
    {
        return (clone $eventsQuery)
            ->pluck('id')
            ->map(fn (mixed $eventId): string => (string) $eventId)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> Empty structure for list responses. */
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
     * Calculates registration summary (total/paid/pending) for the given scope.
     *
     * @param  list<string>  $eventIds  Accessible events.
     * @param  string|null  $eventId  Specific event filter.
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
     * Applies keyword search across multiple related entities.
     *
     * @param  Builder  $query  Registration query.
     * @param  string  $search  Search term.
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
}

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

class StaffRegistrationService
{
    public function __construct(private readonly RegistrationStatsService $registrationStats) {}

    /** @return Collection<int, Event> */
    public function eventsForOrganizer(User $organizer): Collection
    {
        $this->ensureOrganizer($organizer);

        return $this->eventsWithCounts(
            $this->organizerEventsQuery($organizer)
                ->orderBy('start_at', 'asc')
                ->get($this->eventSelect()),
        );
    }

    /** @return Collection<int, Event> */
    public function eventsForAdmin(User $admin): Collection
    {
        $this->ensureAdmin($admin);

        return $this->eventsWithCounts(
            $this->adminEventsQuery($admin)
                ->orderBy('start_at', 'desc')
                ->get($this->eventSelect()),
        );
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function listForOrganizer(User $organizer, array $filters): array
    {
        $this->ensureOrganizer($organizer);

        return $this->list($this->organizerEventsQuery($organizer), $filters);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function listForAdmin(User $admin, array $filters): array
    {
        $this->ensureAdmin($admin);

        return $this->list($this->adminEventsQuery($admin), $filters);
    }

    public function deleteForOrganizer(User $organizer, Registration $registration): void
    {
        $this->ensureOrganizer($organizer);
        $this->delete($registration, $this->organizerEventsQuery($organizer));
    }

    public function deleteForAdmin(User $admin, Registration $registration): void
    {
        $this->ensureAdmin($admin);
        $this->delete($registration, $this->adminEventsQuery($admin));
    }

    /** @return Builder<Event> */
    private function organizerEventsQuery(User $user): Builder
    {
        return Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->where(function ($query) use ($user): void {
                $query->where('organizer_id', $user->getKey())
                    ->orWhere('created_by', $user->getKey());
            });
    }

    /** @return Builder<Event> */
    private function adminEventsQuery(User $user): Builder
    {
        return Event::query()->where(function ($query) use ($user): void {
            $query->where('organizer_id', $user->getKey())
                ->orWhere('created_by', $user->getKey())
                ->orWhereHas('organizer', fn ($organizer) => $organizer->where('role', User::ROLE_ORGANIZER))
                ->orWhereHas('creator', fn ($creator) => $creator->where('role', User::ROLE_ORGANIZER));
        });
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function list(Builder $eventsQuery, array $filters): array
    {
        $eventIds = $this->eventIds($eventsQuery);

        if ($eventIds === []) {
            return $this->emptyListPayload();
        }

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

        if ($eventId !== null) {
            $registrationsQuery->where('event_id', $eventId);
        }

        $paymentFilter = $filters['payment_status'] ?? 'all';
        if ($paymentFilter !== 'all') {
            $registrationsQuery->where('payment_status', $paymentFilter);
        }

        if (! empty($filters['q'])) {
            $this->applySearch($registrationsQuery, (string) $filters['q']);
        }

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

    private function delete(Registration $registration, Builder $eventsQuery): void
    {
        $eventIds = $this->eventIds($eventsQuery);

        if (! in_array((string) $registration->getAttribute('event_id'), $eventIds, true)) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }

        if ($registration->getAttribute('payment_status') === 'paid') {
            throw new RegistrationException('Impossible de supprimer une inscription déjà payée.');
        }

        DB::transaction(function () use ($registration): void {
            $registration->delete();

            Event::query()
                ->whereKey($registration->getAttribute('event_id'))
                ->where('registered_count', '>', 0)
                ->decrement('registered_count');
        });
    }

    /** @param Collection<int, Event> $events @return Collection<int, Event> */
    private function eventsWithCounts(Collection $events): Collection
    {
        $this->registrationStats->attachCount($events, 'registrations_count');
        $this->registrationStats->attachCount($events, 'paid_registrations_count', 'paid');

        return $events;
    }

    /** @return list<string> */
    private function eventSelect(): array
    {
        return ['id', 'title', 'start_at', 'status', 'registered_count', 'capacity'];
    }

    /** @return list<string> */
    private function eventIds(Builder $eventsQuery): array
    {
        return (clone $eventsQuery)
            ->pluck('id')
            ->map(fn (mixed $eventId): string => (string) $eventId)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
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

    /** @param list<string> $eventIds @return array{total: int, paid: int, pending: int} */
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

    private function ensureOrganizer(User $user): void
    {
        if ($user->getAttribute('role') !== User::ROLE_ORGANIZER) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }

    private function ensureAdmin(User $user): void
    {
        if (! $user->isAdmin()) {
            throw new RegistrationException('Accès refusé pour ce rôle.', 403);
        }
    }
}

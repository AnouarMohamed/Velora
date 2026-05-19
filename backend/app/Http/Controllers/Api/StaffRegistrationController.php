<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrations\StaffRegistrationIndexRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationStatsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffRegistrationController extends Controller
{
    public function __construct(private readonly RegistrationStatsService $registrationStats) {}

    /** Événements publiés assignés à l'organisateur ou créés par lui. */
    private function organizerEventsQuery(User $user): Builder
    {
        return Event::query()
            ->where('status', 'published')
            ->where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                    ->orWhere('created_by', $user->id);
            });
    }

    /** Événements gérés par l'admin : les siens + espace organisateur. */
    private function adminEventsQuery(User $user): Builder
    {
        return Event::query()->where(function ($q) use ($user) {
            $q->where('organizer_id', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhereHas('organizer', fn ($o) => $o->where('role', User::ROLE_ORGANIZER))
                ->orWhereHas('creator', fn ($c) => $c->where('role', User::ROLE_ORGANIZER));
        });
    }

    public function eventsForOrganizer(Request $request)
    {
        abort_unless($request->user()->role === User::ROLE_ORGANIZER, 403);

        $events = $this->organizerEventsQuery($request->user())
            ->orderBy('start_at', 'asc')
            ->get(['id', 'title', 'start_at', 'status', 'registered_count', 'capacity']);

        $this->registrationStats->attachCount($events, 'registrations_count');
        $this->registrationStats->attachCount($events, 'paid_registrations_count', 'paid');

        return response()->json($events);
    }

    public function eventsForAdmin(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $events = $this->adminEventsQuery($request->user())
            ->orderBy('start_at', 'desc')
            ->get(['id', 'title', 'start_at', 'status', 'registered_count', 'capacity']);

        $this->registrationStats->attachCount($events, 'registrations_count');
        $this->registrationStats->attachCount($events, 'paid_registrations_count', 'paid');

        return response()->json($events);
    }

    public function indexForOrganizer(StaffRegistrationIndexRequest $request)
    {
        abort_unless($request->user()->role === User::ROLE_ORGANIZER, 403);

        return $this->index($request, $this->organizerEventsQuery($request->user()));
    }

    public function indexForAdmin(StaffRegistrationIndexRequest $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return $this->index($request, $this->adminEventsQuery($request->user()));
    }

    private function index(StaffRegistrationIndexRequest $request, Builder $eventsQuery)
    {
        $data = $request->validated();

        $eventIds = (clone $eventsQuery)
            ->pluck('id')
            ->map(fn (mixed $eventId): string => (string) $eventId);

        if ($eventIds->isEmpty()) {
            return response()->json([
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
            ]);
        }

        $regsQuery = Registration::query()
            ->whereIn('event_id', $eventIds)
            ->with([
                'event:id,event_request_id,title,description,start_at,end_at,location,room,status,image_path',
                'event.eventRequest:id,image_path',
                'user:id,name,email',
            ]);

        if (! empty($data['event_id'])) {
            abort_unless($eventIds->contains((string) $data['event_id']), 403);
            $regsQuery->where('event_id', $data['event_id']);
        }

        $paymentFilter = $data['payment_status'] ?? 'all';
        if ($paymentFilter !== 'all') {
            $regsQuery->where('payment_status', $paymentFilter);
        }

        if (! empty($data['q'])) {
            $search = $data['q'];
            $regsQuery->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                })->orWhereHas('event', function ($eq) use ($search) {
                    $eq->where('title', 'like', '%'.$search.'%');
                })->orWhere('ticket_code', 'like', '%'.$search.'%');
            });
        }

        $summaryBase = Registration::query()->whereIn('event_id', $eventIds);
        if (! empty($data['event_id'])) {
            $summaryBase->where('event_id', $data['event_id']);
        }

        $summary = [
            'total' => (clone $summaryBase)->count(),
            'paid' => (clone $summaryBase)->where('payment_status', 'paid')->count(),
            'pending' => (clone $summaryBase)->where('payment_status', 'pending')->count(),
        ];

        $paginated = $regsQuery->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'summary' => $summary,
        ]);
    }

    public function destroyForOrganizer(Request $request, Registration $registration)
    {
        abort_unless($request->user()->role === User::ROLE_ORGANIZER, 403);

        return $this->destroy($request, $registration, $this->organizerEventsQuery($request->user()));
    }

    public function destroyForAdmin(Request $request, Registration $registration)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return $this->destroy($request, $registration, $this->adminEventsQuery($request->user()));
    }

    private function destroy(Request $request, Registration $registration, Builder $eventsQuery)
    {
        $eventIds = (clone $eventsQuery)
            ->pluck('id')
            ->map(fn (mixed $eventId): string => (string) $eventId);

        abort_unless($eventIds->contains((string) $registration->event_id), 403);

        if ($registration->payment_status === 'paid') {
            return response()->json([
                'message' => 'Impossible de supprimer une inscription déjà payée.',
            ], 422);
        }

        return DB::transaction(function () use ($registration) {
            $registration->delete();

            Event::query()
                ->whereKey($registration->event_id)
                ->where('registered_count', '>', 0)
                ->decrement('registered_count');

            return response()->json(['message' => 'Inscription supprimée.']);
        });
    }
}

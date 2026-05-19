<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Services\EventRequests\EventRequestEligibilityService;
use App\Services\RegistrationStatsService;
use App\Support\Money;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(
        private readonly RegistrationStatsService $registrationStats,
        private readonly EventRequestEligibilityService $eventRequestEligibility,
    ) {}

    public function admin(Request $request)
    {
        $formatPastEvent = fn (Event $event) => [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'image_url' => $event->image_url,
            'location' => $event->location,
            'start_at' => $event->start_at,
            'end_at' => $event->end_at,
            'ticket_price' => (float) $event->ticket_price,
            'registered_count' => $event->registered_count,
            'capacity' => $event->capacity,
            'tickets_count' => (int) ($event->tickets_count ?? 0),
            'organizer' => $event->organizer,
            'event_request' => $event->eventRequest ? [
                'contact_name' => $event->eventRequest->contact_name,
                'contact_email' => $event->eventRequest->contact_email,
            ] : null,
        ];

        $pastEventModels = Event::query()
            ->where('status', 'published')
            ->finished()
            ->with([
                'organizer:id,name',
                'eventRequest:id,title,contact_name,contact_email',
            ])
            ->get();

        $this->registrationStats->attachCount($pastEventModels, 'tickets_count', 'paid');

        $pastEvents = $pastEventModels
            ->sort(function (Event $a, Event $b) {
                $aEffectiveEnd = strtotime((string) ($a->end_at ?? $a->start_at));
                $bEffectiveEnd = strtotime((string) ($b->end_at ?? $b->start_at));

                if ($aEffectiveEnd === $bEffectiveEnd) {
                    return strtotime((string) $b->start_at) <=> strtotime((string) $a->start_at);
                }

                return $bEffectiveEnd <=> $aEffectiveEnd;
            })
            ->values();

        $pastEventsPayload = [];
        foreach ($pastEvents as $event) {
            $pastEventsPayload[] = $formatPastEvent($event);
        }

        return response()->json([
            'users_total' => User::count(),
            'users_by_role' => collect(User::raw(function ($collection) {
                return $collection->aggregate([
                    ['$group' => ['_id' => '$role', 'count' => ['$sum' => 1]]],
                ]);
            })->toArray())->mapWithKeys(function ($result) {
                $role = data_get($result, '_id');

                return [(string) ($role ?? '') => (int) data_get($result, 'count', 0)];
            }),
            'events_total' => Event::count(),
            'events_published' => Event::where('status', 'published')->count(),
            'registrations_total' => Registration::count(),
            'revenue' => Money::floatFromCents(Payment::where('status', 'completed')->sum('amount_cents')),
            'pending_requests' => EventRequest::where('status', 'pending')->count(),
            'pending_publications' => Event::where('status', 'pending_publication')->count(),
            'past_events' => $pastEventsPayload,
        ]);
    }

    public function client(Request $request)
    {
        $user = $request->user();
        abort_unless($user->role === User::ROLE_CLIENT, 403);

        $eventIds = Event::query()
            ->whereHas('eventRequest', function ($q) use ($user) {
                $q->where('contact_email', $user->email);
            })
            ->pluck('id');

        $requests = EventRequest::query()
            ->where('contact_email', $user->email)
            ->with([
                'event' => fn ($q) => $q
                    ->select('id', 'title', 'status', 'event_request_id', 'ticket_price_cents'),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $this->registrationStats->attachCount(
            $requests->pluck('event')->filter()->values(),
            'registrations_count',
        );

        $eventIdsArray = $eventIds->all();

        $formatRequest = function (EventRequest $req) {
            $data = $req->toArray();
            $data['registrations_count'] = (int) ($req->event?->registrations_count ?? 0);

            return $data;
        };

        $clientEventsQuery = Event::query()
            ->where('status', 'published')
            ->whereHas('eventRequest', function ($q) use ($user) {
                $q->where('contact_email', $user->email)->where('status', 'approved');
            })
            ->with(['organizer:id,name', 'eventRequest']);

        $formatEvent = fn (Event $event) => [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'image_url' => $event->image_url,
            'location' => $event->location,
            'start_at' => $event->start_at,
            'end_at' => $event->end_at,
            'registered_count' => $event->registered_count,
            'capacity' => $event->capacity,
            'tickets_count' => (int) $event->tickets_count,
            'ticket_price' => (float) $event->ticket_price,
            'organizer' => $event->organizer,
        ];

        $featuredEventModels = (clone $clientEventsQuery)
            ->notFinished()
            ->orderBy('start_at', 'asc')
            ->get();

        $this->registrationStats->attachCount($featuredEventModels, 'tickets_count', 'paid');

        $featuredEvents = [];
        foreach ($featuredEventModels as $event) {
            $featuredEvents[] = $formatEvent($event);
        }

        $pastEventModels = (clone $clientEventsQuery)
            ->finished()
            ->orderBy('end_at', 'desc')
            ->orderBy('start_at', 'desc')
            ->get();

        $this->registrationStats->attachCount($pastEventModels, 'tickets_count', 'paid');

        $pastEvents = [];
        foreach ($pastEventModels as $event) {
            $pastEvents[] = $formatEvent($event);
        }

        $blockReason = $this->eventRequestEligibility->blockingReasonFor($user);

        return response()->json([
            'total_revenue' => $eventIdsArray
                ? Money::floatFromCents(Payment::query()
                    ->whereHas('registration', fn ($q) => $q->whereIn('event_id', $eventIdsArray))
                    ->where('status', 'completed')
                    ->sum('amount_cents'))
                : 0.0,
            'featured_events' => $featuredEvents,
            'past_events' => $pastEvents,
            'can_submit_new_request' => $blockReason === null,
            'block_reason' => $blockReason,
            'requests' => [
                'pending' => $requests->where('status', 'pending')->map($formatRequest)->values()->all(),
                'approved' => $requests->where('status', 'approved')->map($formatRequest)->values()->all(),
                'rejected' => $requests->where('status', 'rejected')->map($formatRequest)->values()->all(),
            ],
        ]);
    }
}

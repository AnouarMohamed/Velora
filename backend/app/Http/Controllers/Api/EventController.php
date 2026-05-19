<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\EventManagementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Events\AssignEventOrganizerRequest;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventCapacityRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\EventManagementService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public const STATUS_PENDING_PUBLICATION = Event::STATUS_PENDING_PUBLICATION;

    public function __construct(private readonly EventManagementService $events) {}

    public function indexAll(Request $request)
    {
        $q = Event::query()->with(['organizer', 'eventRequest', 'creator:id,name,role'])->latest();

        if ($search = $request->query('q')) {
            $q->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            });
        }

        return response()->json($q->paginate(30));
    }

    public function indexMine(Request $request)
    {
        $user = $request->user();
        $events = Event::query()
            ->where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                    ->orWhere('created_by', $user->id);
            })
            ->with(['eventRequest', 'organizer'])
            ->latest()
            ->paginate(30);

        return response()->json($events);
    }

    /** Événements assignés à un organisateur ou créés par un organisateur. */
    public function indexOrganizerSpace(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $events = Event::query()
            ->where(function ($q) {
                $q->whereHas('organizer', fn ($q) => $q->where('role', User::ROLE_ORGANIZER))
                    ->orWhereHas('creator', fn ($q) => $q->where('role', User::ROLE_ORGANIZER));
            })
            ->with(['organizer', 'eventRequest', 'creator:id,name,role'])
            ->latest()
            ->paginate(30);

        return response()->json($events);
    }

    /** Événements assignés à l'admin ou créés par l'admin. */
    public function indexAssignedToMe(Request $request)
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403);

        $events = Event::query()
            ->where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                    ->orWhere('created_by', $user->id);
            })
            ->with(['eventRequest', 'organizer'])
            ->latest()
            ->paginate(30);

        return response()->json($events);
    }

    public function browsePublished(Request $request)
    {
        $q = Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->where('start_at', '>=', now()->subDay())
            ->with(['organizer', 'eventRequest'])
            ->orderBy('start_at');

        if ($search = $request->query('q')) {
            $q->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            });
        }

        return response()->json($q->paginate(20));
    }

    public function show(Request $request, Event $event)
    {
        if ($event->status !== Event::STATUS_PUBLISHED && ! $this->canManage($request, $event)) {
            abort(404);
        }

        return response()->json($event->load(['organizer', 'eventRequest', 'tasks', 'activities']));
    }

    public function store(StoreEventRequest $request)
    {
        try {
            $event = $this->events->create($request->user(), $request->validated());
        } catch (ValidationException $e) {
            return $this->validationResponse($e);
        } catch (EventManagementException $e) {
            return response()->json($e->toResponsePayload(), $e->status);
        }

        return response()->json($event, 201);
    }

    public function update(UpdateEventRequest $request, Event $event)
    {
        try {
            $event = $this->events->update($request->user(), $event, $request->validated());
        } catch (EventManagementException $e) {
            return response()->json($e->toResponsePayload(), $e->status);
        }

        return response()->json($event);
    }

    public function updateCapacity(UpdateEventCapacityRequest $request, Event $event)
    {
        try {
            $event = $this->events->updateCapacity($request->user(), $event, (int) $request->validated('capacity'));
        } catch (EventManagementException $e) {
            return response()->json($e->toResponsePayload(), $e->status);
        }

        return response()->json($event);
    }

    public function assignOrganizer(AssignEventOrganizerRequest $request, Event $event)
    {
        $event = $this->events->assignOrganizer($event, $request->validated('organizer_id'));

        return response()->json($event);
    }

    public function destroy(Request $request, Event $event)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $event->delete();

        return response()->json(null, 204);
    }

    /** Organisateur : soumet l'événement à validation admin avant mise en ligne. */
    public function requestPublication(Request $request, Event $event)
    {
        try {
            $event = $this->events->requestPublication($request->user(), $event);
        } catch (EventManagementException $e) {
            return response()->json($e->toResponsePayload(), $e->status);
        }

        return response()->json($event);
    }

    /** Admin : approuve la demande de publication d'un organisateur. */
    public function approvePublication(Request $request, Event $event)
    {
        try {
            $event = $this->events->approvePublication($request->user(), $event);
        } catch (EventManagementException $e) {
            return response()->json($e->toResponsePayload(), $e->status);
        }

        return response()->json($event);
    }

    private function canManage(Request $request, Event $event): bool
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return true;
        }

        return $event->isOrganizer($user);
    }

    private function validationResponse(ValidationException $e)
    {
        return response()->json([
            'message' => collect($e->errors())->flatten()->first(),
            'errors' => $e->errors(),
        ], 422);
    }
}

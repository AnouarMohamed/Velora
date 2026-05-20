<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Events\AssignEventOrganizerRequest;
use App\Http\Requests\Events\EventIndexRequest;
use App\Http\Requests\Events\StoreEventRequest;
use App\Http\Requests\Events\UpdateEventCapacityRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Models\Event;
use App\Models\User;
use App\Services\EventManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing events.
 *
 * This controller handles the lifecycle of events, including creation, updates,
 * publication workflows, and participant browsing.
 * Access control is a mix of controller-level checks and service-level logic.
 */
class EventController extends Controller
{
    public const STATUS_PENDING_PUBLICATION = Event::STATUS_PENDING_PUBLICATION;

    /**
     * @param  EventManagementService  $events  Service for event business logic.
     */
    public function __construct(private readonly EventManagementService $events) {}

    /**
     * List all events (Admin view).
     *
     * Provides a paginated list of all events with their organizers and creators.
     * Supports searching by title, description, or location.
     *
     * @return JsonResponse Paginated list of events.
     */
    public function indexAll(EventIndexRequest $request)
    {
        $q = Event::query()
            ->with(['organizer', 'eventRequest', 'creator:id,name,role'])
            ->orderBy('created_at', 'desc');

        if ($search = $request->validated('q')) {
            $q->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            });
        }

        return response()->json($q->paginate(30));
    }

    /**
     * List events managed by or created by the current user.
     *
     * @return JsonResponse Paginated list of user-related events.
     */
    public function indexMine(Request $request)
    {
        $user = $request->user();
        $events = Event::query()
            ->where(function ($q) use ($user) {
                $q->where('organizer_id', $user->id)
                    ->orWhere('created_by', $user->id);
            })
            ->with(['eventRequest', 'organizer'])
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return response()->json($events);
    }

    /**
     * List events assigned to or created by any Organizer (Admin view).
     *
     * @return JsonResponse
     */
    public function indexOrganizerSpace(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $events = Event::query()
            ->where(function ($q) {
                $q->whereHas('organizer', fn ($q) => $q->where('role', User::ROLE_ORGANIZER))
                    ->orWhereHas('creator', fn ($q) => $q->where('role', User::ROLE_ORGANIZER));
            })
            ->with(['organizer', 'eventRequest', 'creator:id,name,role'])
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return response()->json($events);
    }

    /**
     * List events specifically assigned to the current Admin.
     *
     * @return JsonResponse
     */
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
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return response()->json($events);
    }

    /**
     * Browse published events (Public view).
     *
     * Only returns events with STATUS_PUBLISHED that haven't ended more than a day ago.
     * Supports searching.
     *
     * @return JsonResponse Paginated list of published events.
     */
    public function browsePublished(EventIndexRequest $request)
    {
        $q = Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->where('start_at', '>=', now()->subDay())
            ->with(['organizer', 'eventRequest'])
            ->orderBy('start_at', 'asc');

        if ($search = $request->validated('q')) {
            $q->where(function ($query) use ($search) {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            });
        }

        return response()->json($q->paginate(20));
    }

    /**
     * Get details for a single event.
     *
     * Non-published events are only visible to their managers or admins.
     *
     * @return JsonResponse Event details with relations.
     */
    public function show(Request $request, Event $event)
    {
        if ($event->status !== Event::STATUS_PUBLISHED && ! $this->canManage($request, $event)) {
            abort(404);
        }

        return response()->json($event->load(['organizer', 'eventRequest', 'tasks', 'activities']));
    }

    /**
     * Create a new event.
     *
     * @param  StoreEventRequest  $request  Validated event data.
     * @return JsonResponse 201 Created.
     */
    public function store(StoreEventRequest $request)
    {
        $event = $this->events->create($request->user(), $request->validated());

        return response()->json($event, 201);
    }

    /**
     * Update event details.
     *
     * @param  UpdateEventRequest  $request  Validated event updates.
     * @return JsonResponse Updated event.
     */
    public function update(UpdateEventRequest $request, Event $event)
    {
        $event = $this->events->update($request->user(), $event, $request->validated());

        return response()->json($event);
    }

    /**
     * Update the participant capacity of an event.
     *
     * @param  UpdateEventCapacityRequest  $request  Validated capacity.
     * @return JsonResponse Updated event.
     */
    public function updateCapacity(UpdateEventCapacityRequest $request, Event $event)
    {
        $event = $this->events->updateCapacity($request->user(), $event, (int) $request->validated('capacity'));

        return response()->json($event);
    }

    /**
     * Assign a specific organizer to manage an event.
     *
     * @param  AssignEventOrganizerRequest  $request  Validated organizer_id.
     * @return JsonResponse Updated event.
     */
    public function assignOrganizer(AssignEventOrganizerRequest $request, Event $event)
    {
        $event = $this->events->assignOrganizer($event, $request->validated('organizer_id'));

        return response()->json($event);
    }

    /**
     * Delete an event. (Admin only)
     *
     * @return JsonResponse 204 No Content.
     */
    public function destroy(Request $request, Event $event)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $event->delete();

        return response()->json(null, 204);
    }

    /**
     * Request event publication.
     *
     * Typically called by an Organizer when they finish planning.
     * Changes status to PENDING_PUBLICATION.
     *
     * @return JsonResponse Updated event.
     */
    public function requestPublication(Request $request, Event $event)
    {
        $event = $this->events->requestPublication($request->user(), $event);

        return response()->json($event);
    }

    /**
     * Approve event publication. (Admin only)
     *
     * Changes status to PUBLISHED, making it visible to everyone.
     *
     * @return JsonResponse Updated event.
     */
    public function approvePublication(Request $request, Event $event)
    {
        $event = $this->events->approvePublication($request->user(), $event);

        return response()->json($event);
    }

    /**
     * Internal helper to check if the current user can manage a specific event.
     *
     * @return bool True if Admin or the assigned Organizer.
     */
    private function canManage(Request $request, Event $event): bool
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return true;
        }

        return $event->isOrganizer($user);
    }
}

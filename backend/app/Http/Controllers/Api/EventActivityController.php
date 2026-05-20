<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventActivities\StoreEventActivityRequest;
use App\Http\Requests\EventActivities\UpdateEventActivityRequest;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use App\Services\Events\EventActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Controller for managing event activities (program/schedule).
 *
 * Activities are specific items in an event's schedule (e.g., workshops, speeches).
 * This controller delegates business logic and authorization checks to the EventActivityService.
 */
class EventActivityController extends Controller
{
    /**
     * @param  EventActivityService  $activities  Service for activity management.
     */
    public function __construct(private readonly EventActivityService $activities) {}

    /**
     * List all activities for a specific event.
     *
     * @param  Event  $event  The event parent of these activities.
     * @return JsonResponse List of activities.
     */
    public function index(Request $request, Event $event)
    {
        // Service handles authorization: usually only organizers or admins can see all details
        return response()->json($this->activities->list($this->actor($request), $event));
    }

    /**
     * Create a new activity for an event.
     *
     * @param  StoreEventActivityRequest  $request  Validated activity data.
     * @param  Event  $event  The event to which the activity will be added.
     * @return JsonResponse 201 Created with the new activity.
     */
    public function store(StoreEventActivityRequest $request, Event $event)
    {
        $activity = $this->activities->create($this->actor($request), $event, $request->validated());

        return response()->json($activity, 201);
    }

    /**
     * Update an existing activity.
     *
     * @param  UpdateEventActivityRequest  $request  Validated activity updates.
     * @param  Event  $event  The parent event.
     * @param  EventActivity  $eventActivity  The activity to update.
     * @return JsonResponse The updated activity.
     */
    public function update(UpdateEventActivityRequest $request, Event $event, EventActivity $eventActivity)
    {
        return response()->json($this->activities->update($this->actor($request), $event, $eventActivity, $request->validated()));
    }

    /**
     * Remove an activity from an event.
     *
     * @param  Event  $event  The parent event.
     * @param  EventActivity  $eventActivity  The activity to delete.
     * @return JsonResponse 204 No Content.
     */
    public function destroy(Request $request, Event $event, EventActivity $eventActivity)
    {
        $this->activities->delete($this->actor($request), $event, $eventActivity);

        return response()->json(null, 204);
    }

    /**
     * Retrieve and validate the authenticated user from the request.
     *
     * @return User The authenticated user.
     *
     * @throws HttpException 401 if not authenticated.
     */
    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}

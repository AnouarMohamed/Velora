<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventTasks\StoreEventTaskRequest;
use App\Http\Requests\EventTasks\UpdateEventTaskRequest;
use App\Models\Event;
use App\Models\EventTask;
use App\Models\User;
use App\Services\Events\EventTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing event tasks.
 *
 * Tasks are internal to-do items for organizers to manage the event preparation.
 * This controller delegates all business logic and authorization to the EventTaskService.
 */
class EventTaskController extends Controller
{
    /**
     * @param  EventTaskService  $tasks  Service for task management.
     */
    public function __construct(private readonly EventTaskService $tasks) {}

    /**
     * List all tasks for a specific event.
     *
     * @param  Event  $event  Parent event.
     * @return JsonResponse List of tasks.
     */
    public function index(Request $request, Event $event)
    {
        // Service ensures only authorized users (Admin/Organizer) can see tasks
        return response()->json($this->tasks->list($this->actor($request), $event));
    }

    /**
     * Create a new task for an event.
     *
     * @param  StoreEventTaskRequest  $request  Validated task data.
     * @param  Event  $event  Parent event.
     * @return JsonResponse 201 Created with the new task.
     */
    public function store(StoreEventTaskRequest $request, Event $event)
    {
        $task = $this->tasks->create($this->actor($request), $event, $request->validated());

        return response()->json($task, 201);
    }

    /**
     * Update an existing task.
     *
     * Used for changing title, status (completed/pending), etc.
     *
     * @param  UpdateEventTaskRequest  $request  Validated task updates.
     * @param  Event  $event  Parent event.
     * @param  EventTask  $eventTask  Task to update.
     * @return JsonResponse Updated task.
     */
    public function update(UpdateEventTaskRequest $request, Event $event, EventTask $eventTask)
    {
        return response()->json($this->tasks->update($this->actor($request), $event, $eventTask, $request->validated()));
    }

    /**
     * Delete a task.
     *
     * @param  Event  $event  Parent event.
     * @param  EventTask  $eventTask  Task to delete.
     * @return JsonResponse 204 No Content.
     */
    public function destroy(Request $request, Event $event, EventTask $eventTask)
    {
        $this->tasks->delete($this->actor($request), $event, $eventTask);

        return response()->json(null, 204);
    }

    /**
     * Retrieve and validate the authenticated user.
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

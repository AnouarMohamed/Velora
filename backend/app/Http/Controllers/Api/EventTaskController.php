<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventTasks\StoreEventTaskRequest;
use App\Http\Requests\EventTasks\UpdateEventTaskRequest;
use App\Models\Event;
use App\Models\EventTask;
use App\Models\User;
use App\Services\Events\EventTaskService;
use Illuminate\Http\Request;

class EventTaskController extends Controller
{
    public function __construct(private readonly EventTaskService $tasks) {}

    public function index(Request $request, Event $event)
    {
        return response()->json($this->tasks->list($this->actor($request), $event));
    }

    public function store(StoreEventTaskRequest $request, Event $event)
    {
        $task = $this->tasks->create($this->actor($request), $event, $request->validated());

        return response()->json($task, 201);
    }

    public function update(UpdateEventTaskRequest $request, Event $event, EventTask $eventTask)
    {
        return response()->json($this->tasks->update($this->actor($request), $event, $eventTask, $request->validated()));
    }

    public function destroy(Request $request, Event $event, EventTask $eventTask)
    {
        $this->tasks->delete($this->actor($request), $event, $eventTask);

        return response()->json(null, 204);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}

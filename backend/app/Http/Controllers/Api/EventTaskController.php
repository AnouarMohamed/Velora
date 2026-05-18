<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTask;
use Illuminate\Http\Request;

class EventTaskController extends Controller
{
    public function index(Request $request, Event $event)
    {
        abort_unless($this->canManage($request, $event), 403);

        return response()->json($event->tasks()->orderBy('due_at')->get());
    }

    public function store(Request $request, Event $event)
    {
        abort_unless($this->canManage($request, $event), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = $event->tasks()->create($data + ['is_done' => false]);

        return response()->json($task, 201);
    }

    public function update(Request $request, Event $event, EventTask $eventTask)
    {
        abort_unless($eventTask->event_id === $event->id, 404);
        abort_unless($this->canManage($request, $event), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_done' => ['sometimes', 'boolean'],
            'due_at' => ['nullable', 'date'],
        ]);

        $eventTask->update($data);

        return response()->json($eventTask->fresh());
    }

    public function destroy(Request $request, Event $event, EventTask $eventTask)
    {
        abort_unless($eventTask->event_id === $event->id, 404);
        abort_unless($this->canManage($request, $event), 403);
        $eventTask->delete();

        return response()->json(null, 204);
    }

    private function canManage(Request $request, Event $event): bool
    {
        $user = $request->user();

        return $user->isAdmin() || $event->isOrganizer($user);
    }
}

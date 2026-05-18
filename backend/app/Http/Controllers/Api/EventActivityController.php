<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventActivity;
use Illuminate\Http\Request;

class EventActivityController extends Controller
{
    public function index(Request $request, Event $event)
    {
        abort_unless($this->canManage($request, $event), 403);

        return response()->json($event->activities()->orderBy('sort_order')->orderBy('starts_at')->get());
    }

    public function store(Request $request, Event $event)
    {
        abort_unless($this->canManage($request, $event), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $activity = $event->activities()->create($data + ['sort_order' => $data['sort_order'] ?? 0]);

        return response()->json($activity, 201);
    }

    public function update(Request $request, Event $event, EventActivity $eventActivity)
    {
        abort_unless($eventActivity->event_id === $event->id, 404);
        abort_unless($this->canManage($request, $event), 403);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $eventActivity->update($data);

        return response()->json($eventActivity->fresh());
    }

    public function destroy(Request $request, Event $event, EventActivity $eventActivity)
    {
        abort_unless($eventActivity->event_id === $event->id, 404);
        abort_unless($this->canManage($request, $event), 403);
        $eventActivity->delete();

        return response()->json(null, 204);
    }

    private function canManage(Request $request, Event $event): bool
    {
        $user = $request->user();

        return $user->isAdmin() || $event->isOrganizer($user);
    }
}

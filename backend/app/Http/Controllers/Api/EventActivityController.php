<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventActivities\StoreEventActivityRequest;
use App\Http\Requests\EventActivities\UpdateEventActivityRequest;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use App\Services\Events\EventActivityService;
use Illuminate\Http\Request;

class EventActivityController extends Controller
{
    public function __construct(private readonly EventActivityService $activities) {}

    public function index(Request $request, Event $event)
    {
        return response()->json($this->activities->list($this->actor($request), $event));
    }

    public function store(StoreEventActivityRequest $request, Event $event)
    {
        $activity = $this->activities->create($this->actor($request), $event, $request->validated());

        return response()->json($activity, 201);
    }

    public function update(UpdateEventActivityRequest $request, Event $event, EventActivity $eventActivity)
    {
        return response()->json($this->activities->update($this->actor($request), $event, $eventActivity, $request->validated()));
    }

    public function destroy(Request $request, Event $event, EventActivity $eventActivity)
    {
        $this->activities->delete($this->actor($request), $event, $eventActivity);

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

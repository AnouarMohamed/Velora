<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequests\ReviewEventRequestRequest;
use App\Http\Requests\EventRequests\StoreClientEventRequest;
use App\Models\EventRequest;
use App\Models\User;
use App\Services\EventRequestReviewService;
use App\Services\EventRequests\EventRequestSubmissionService;
use Illuminate\Http\Request;

class EventRequestController extends Controller
{
    public function __construct(
        private readonly EventRequestSubmissionService $submissions,
        private readonly EventRequestReviewService $reviews,
    ) {}

    public function store(StoreClientEventRequest $request)
    {
        $eventRequest = $this->submissions->submit($this->actor($request), $request->validated());

        return response()->json($eventRequest, 201);
    }

    public function destroy(Request $request, EventRequest $eventRequest)
    {
        $this->submissions->delete($this->actor($request), $eventRequest);

        return response()->json(['message' => 'Demande supprimée.']);
    }

    public function index(Request $request)
    {
        $query = EventRequest::query()
            ->with('event')
            ->orderBy('created_at', 'desc');

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(20));
    }

    public function review(ReviewEventRequestRequest $request, EventRequest $eventRequest)
    {
        $data = $request->validated();

        if ($data['decision'] === EventRequest::STATUS_REJECTED) {
            return response()->json($this->reviews->reject(
                $eventRequest,
                $this->actor($request),
                $data['rejection_reason'] ?? null,
            ));
        }

        return response()->json($this->reviews->approve($eventRequest, $this->actor($request)));
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

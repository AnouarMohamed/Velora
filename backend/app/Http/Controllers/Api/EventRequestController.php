<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequests\EventRequestIndexRequest;
use App\Http\Requests\EventRequests\ReviewEventRequestRequest;
use App\Http\Requests\EventRequests\StoreClientEventRequest;
use App\Models\EventRequest;
use App\Models\User;
use App\Services\EventRequestReviewService;
use App\Services\EventRequests\EventRequestSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing event requests from clients.
 *
 * Clients use this to propose new events. Admins use this to review (approve/reject) those requests.
 * Approved requests usually lead to the creation of an Event.
 */
class EventRequestController extends Controller
{
    /**
     * @param  EventRequestSubmissionService  $submissions  Service for submitting and managing requests.
     * @param  EventRequestReviewService  $reviews  Service for reviewing (approving/rejecting) requests.
     */
    public function __construct(
        private readonly EventRequestSubmissionService $submissions,
        private readonly EventRequestReviewService $reviews,
    ) {}

    /**
     * Submit a new event request.
     *
     * Typically called by a Client.
     *
     * @param  StoreClientEventRequest  $request  Validated request data.
     * @return JsonResponse 201 Created.
     */
    public function store(StoreClientEventRequest $request)
    {
        $eventRequest = $this->submissions->submit($this->actor($request), $request->validated());

        return response()->json($eventRequest, 201);
    }

    /**
     * Delete an event request.
     *
     * @return JsonResponse 200 OK message.
     */
    public function destroy(Request $request, EventRequest $eventRequest)
    {
        $this->submissions->delete($this->actor($request), $eventRequest);

        return response()->json(['message' => 'Demande supprimée.']);
    }

    /**
     * List event requests (Admin view).
     *
     * @return JsonResponse Paginated list of requests.
     */
    public function index(EventRequestIndexRequest $request)
    {
        $query = EventRequest::query()
            ->with('event')
            ->orderBy('created_at', 'desc');

        // Optional filtering by status (pending, approved, rejected)
        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Review an event request (Approve or Reject).
     *
     * Only Admins should access this endpoint (enforced via middleware usually).
     *
     * @param  ReviewEventRequestRequest  $request  Validated decision and optional reason.
     * @return JsonResponse Updated request or created event.
     */
    public function review(ReviewEventRequestRequest $request, EventRequest $eventRequest)
    {
        $data = $request->validated();

        // Handle rejection
        if ($data['decision'] === EventRequest::STATUS_REJECTED) {
            return response()->json($this->reviews->reject(
                $eventRequest,
                $this->actor($request),
                $data['rejection_reason'] ?? null,
            ));
        }

        // Handle approval (this usually triggers event creation)
        return response()->json($this->reviews->approve($eventRequest, $this->actor($request)));
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

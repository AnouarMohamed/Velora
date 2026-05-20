<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedbacks\StoreFeedbackRequest;
use App\Http\Resources\FeedbackResource;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\User;
use App\Services\Feedbacks\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller for managing event feedback (reviews).
 *
 * Participants can submit feedback for events they attended.
 * Feedback may require Admin approval before becoming public.
 */
class FeedbackController extends Controller
{
    /**
     * @param  FeedbackService  $feedbacks  Service for feedback business logic.
     */
    public function __construct(private readonly FeedbackService $feedbacks) {}

    /**
     * List feedback for a specific event.
     *
     * @return AnonymousResourceCollection Collection of FeedbackResource.
     */
    public function index(Request $request, Event $event)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        // Service handles visibility: public feedbacks for everyone, all feedbacks for admins.
        return FeedbackResource::collection($this->feedbacks->listForEvent($user, $event));
    }

    /**
     * Submit new feedback for an event.
     *
     * @param  StoreFeedbackRequest  $request  Validated feedback (rating, comment).
     * @return JsonResponse 201 Created with FeedbackResource and a success message.
     */
    public function store(StoreFeedbackRequest $request, Event $event)
    {
        $feedback = $this->feedbacks->submit($request->user(), $event, $request->validated());

        return FeedbackResource::make($feedback)
            ->additional(['message' => 'Votre avis a bien été envoyé. Il sera visible après validation par notre équipe.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Approve a feedback entry (Admin only).
     *
     * Makes the feedback visible to the public.
     *
     * @return FeedbackResource Updated feedback.
     */
    public function approve(Request $request, Feedback $feedback)
    {
        $result = $this->feedbacks->approve($request->user(), $feedback);

        return FeedbackResource::make($result->feedback)
            ->additional(['message' => $result->message]);
    }

    /**
     * Delete a feedback entry.
     *
     * @return JsonResponse 204 No Content.
     */
    public function destroy(Request $request, Feedback $feedback)
    {
        $this->feedbacks->delete($request->user(), $feedback);

        return response()->json(null, 204);
    }
}

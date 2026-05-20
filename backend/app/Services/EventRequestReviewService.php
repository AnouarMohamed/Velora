<?php

namespace App\Services;

use App\Exceptions\EventRequestReviewException;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service handling the moderation workflow for Event Requests.
 *
 * This service manages the transition of a request from 'pending' to either 'approved' or 'rejected'.
 * Approved requests automatically trigger the creation of a corresponding Event draft.
 */
class EventRequestReviewService
{
    /**
     * Rejects an event request with an optional reason.
     *
     * @param  EventRequest  $eventRequest  The request to be rejected.
     * @param  User  $reviewer  The admin user performing the review.
     * @param  string|null  $reason  Optional feedback explaining why the request was rejected.
     * @return EventRequest The updated request instance.
     *
     * @throws EventRequestReviewException If the reviewer is not authorized or request was already handled.
     */
    public function reject(EventRequest $eventRequest, User $reviewer, ?string $reason): EventRequest
    {
        $this->ensureReviewerIsAdmin($reviewer);

        // Update the request status and log review metadata.
        $reviewedRequest = $this->markReviewed($eventRequest, [
            'status' => EventRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by_id' => $reviewer->getKey(),
        ]);

        // Notify the client about the rejection.
        NotificationService::eventRequestReviewed($reviewedRequest, EventRequest::STATUS_REJECTED);

        return $reviewedRequest;
    }

    /**
     * Approves an event request and spawns a new Event draft.
     *
     * @param  EventRequest  $eventRequest  The request to be approved.
     * @param  User  $reviewer  The admin user performing the review.
     * @return array{event_request: EventRequest, event: Event}
     *
     * @throws EventRequestReviewException If the reviewer is not authorized or request was already handled.
     */
    public function approve(EventRequest $eventRequest, User $reviewer): array
    {
        $this->ensureReviewerIsAdmin($reviewer);

        // We use a transaction to ensure that the request update and event creation happen atomically.
        return DB::transaction(function () use ($eventRequest, $reviewer) {
            // Update the request status to approved.
            $reviewedRequest = $this->markReviewed($eventRequest, [
                'status' => EventRequest::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by_id' => $reviewer->getKey(),
            ]);

            // Determine the initial event timing. If preferred dates are missing,
            // we default to 1 week from now for a 4-hour duration.
            $start = $reviewedRequest->getAttribute('preferred_start');
            if (! $start instanceof Carbon) {
                $start = now()->addWeek();
            }

            $end = $reviewedRequest->getAttribute('preferred_end');
            if (! $end instanceof Carbon) {
                $end = $start->copy()->addHours(4);
            }

            // Transform the request data into a concrete Event draft.
            // The event is created in 'draft' status, requiring further setup by an organizer.
            $event = Event::create([
                'event_request_id' => $reviewedRequest->getKey(),
                'organizer_id' => null, // To be assigned later by an admin.
                'created_by' => $reviewer->getKey(),
                'title' => $reviewedRequest->getAttribute('title'),
                'description' => $reviewedRequest->getAttribute('description'),
                'image_path' => $reviewedRequest->getAttribute('image_path'),
                'location' => $reviewedRequest->getAttribute('location'),
                'start_at' => $start,
                'end_at' => $end,
                'capacity' => 100, // Default capacity, can be adjusted.
                'registered_count' => 0,
                'ticket_price' => $reviewedRequest->getAttribute('ticket_price') ?? 0,
                'status' => Event::STATUS_DRAFT,
            ]);

            // Notify the client that their request is now becoming an actual event.
            NotificationService::eventRequestReviewed($reviewedRequest, EventRequest::STATUS_APPROVED);

            return [
                'event_request' => $reviewedRequest,
                'event' => $event->load('eventRequest'),
            ];
        });
    }

    /**
     * Atomically marks a request as reviewed if it is still in pending status.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws EventRequestReviewException If the request was already processed by someone else.
     */
    private function markReviewed(EventRequest $eventRequest, array $attributes): EventRequest
    {
        // We use a specific 'where' clause on status to prevent race conditions (double approval/rejection).
        $updated = EventRequest::query()
            ->whereKey($eventRequest->getKey())
            ->where('status', EventRequest::STATUS_PENDING)
            ->update($attributes);

        if (! $updated) {
            throw new EventRequestReviewException('Cette demande a déjà été traitée.');
        }

        $eventRequest->refresh();

        return $eventRequest;
    }

    /**
     * Enforces administrative authorization.
     *
     * @throws EventRequestReviewException
     */
    private function ensureReviewerIsAdmin(User $reviewer): void
    {
        if (! $reviewer->isAdmin()) {
            throw new EventRequestReviewException('Accès refusé pour ce rôle.', 403);
        }
    }
}

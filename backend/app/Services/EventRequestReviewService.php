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
 * The review operation is intentionally centralized here because approval changes two collections:
 * the original request and, when approved, the new event.
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

        // Rejection keeps the request as audit history and records who made the decision.
        $reviewedRequest = $this->markReviewed($eventRequest, [
            'status' => EventRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by_id' => $reviewer->getKey(),
        ]);

        // The client needs a notification because rejected requests do not create an event record.
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

        // Approval updates the request and creates an event; both changes must commit together.
        return DB::transaction(function () use ($eventRequest, $reviewer) {
            // The conditional update inside markReviewed prevents double approval/rejection races.
            $reviewedRequest = $this->markReviewed($eventRequest, [
                'status' => EventRequest::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by_id' => $reviewer->getKey(),
            ]);

            // Client preferred dates are optional in older/demo data, so approval has safe defaults.
            $start = $reviewedRequest->getAttribute('preferred_start');
            if (! $start instanceof Carbon) {
                $start = now()->addWeek();
            }

            $end = $reviewedRequest->getAttribute('preferred_end');
            if (! $end instanceof Carbon) {
                $end = $start->copy()->addHours(4);
            }

            // Approved requests become draft events so staff can still assign an organizer and refine details.
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

            // Notify after the event exists so frontend links can point to the created record when needed.
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
        // This is the workflow lock: only a still-pending request can be reviewed.
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

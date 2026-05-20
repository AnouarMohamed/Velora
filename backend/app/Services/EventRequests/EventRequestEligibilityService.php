<?php

namespace App\Services\EventRequests;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;

/**
 * Service responsible for determining if a user is eligible to submit a new event request.
 *
 * This service enforces the business rule that a client can only have one "active" event-related process
 * at a time (either a pending request or an ongoing event).
 */
class EventRequestEligibilityService
{
    /**
     * Checks if a user is blocked from submitting a new event request and returns the reason.
     *
     * @param  User  $client  The user (client) to check eligibility for.
     * @return string|null Returns a string identifier for the reason if blocked, or null if eligible.
     */
    public function blockingReasonFor(User $client): ?string
    {
        return $this->blockingReasonForEmail((string) $client->getAttribute('email'));
    }

    /**
     * Checks eligibility based on the contact email provided.
     *
     * Eligibility rules:
     * 1. If there is a PENDING request with this email, the user is blocked ('pending').
     * 2. If there are APPROVED requests, all associated events must be PUBLISHED and FINISHED.
     *    - If an approved request exists but no event is linked to it yet, it is considered an active process ('active_event').
     *    - If an event exists but is not published or not finished, it is considered active ('active_event').
     *
     * @param  string  $email  The contact email to verify.
     * @return string|null Returns 'pending', 'active_event', or null if eligible.
     */
    public function blockingReasonForEmail(string $email): ?string
    {
        // Rule 1: Prevent multiple pending requests from the same user/email.
        if (EventRequest::query()
            ->where('contact_email', $email)
            ->where('status', EventRequest::STATUS_PENDING)
            ->exists()
        ) {
            return 'pending';
        }

        // Rule 2: Check for ongoing events linked to previously approved requests.
        $approvedRequestIds = EventRequest::query()
            ->where('contact_email', $email)
            ->where('status', EventRequest::STATUS_APPROVED)
            ->pluck('id')
            ->all();

        // If no requests were ever approved, they are eligible.
        if ($approvedRequestIds === []) {
            return null;
        }

        // Fetch all events associated with these approved requests.
        $eventsByRequestId = Event::query()
            ->whereIn('event_request_id', $approvedRequestIds)
            ->get()
            ->keyBy(fn (Event $event) => (string) $event->getAttribute('event_request_id'));

        foreach ($approvedRequestIds as $approvedRequestId) {
            $event = $eventsByRequestId->get((string) $approvedRequestId);

            // Edge Case: If a request is approved but the Event record hasn't been created/linked yet,
            // we treat it as an active process to avoid race conditions or orphaned requests.
            if (! $event instanceof Event) {
                return 'active_event';
            }

            // An event is considered "done" only if it's PUBLISHED (archived state) and its end date has passed.
            // If it's still in DRAFT or the end date is in the future, it's "active".
            if ($event->getAttribute('status') !== Event::STATUS_PUBLISHED || ! $event->isFinished()) {
                return 'active_event';
            }
        }

        // If all previous approved requests have led to finished events, the user is eligible again.
        return null;
    }
}

<?php

namespace App\Services\EventRequests;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;

class EventRequestEligibilityService
{
    public function blockingReasonFor(User $client): ?string
    {
        return $this->blockingReasonForEmail((string) $client->getAttribute('email'));
    }

    public function blockingReasonForEmail(string $email): ?string
    {
        if (EventRequest::query()
            ->where('contact_email', $email)
            ->where('status', EventRequest::STATUS_PENDING)
            ->exists()
        ) {
            return 'pending';
        }

        $approvedRequestIds = EventRequest::query()
            ->where('contact_email', $email)
            ->where('status', EventRequest::STATUS_APPROVED)
            ->pluck('id')
            ->all();

        if ($approvedRequestIds === []) {
            return null;
        }

        $eventsByRequestId = Event::query()
            ->whereIn('event_request_id', $approvedRequestIds)
            ->get()
            ->keyBy(fn (Event $event) => (string) $event->getAttribute('event_request_id'));

        foreach ($approvedRequestIds as $approvedRequestId) {
            $event = $eventsByRequestId->get((string) $approvedRequestId);

            if (! $event instanceof Event) {
                return 'active_event';
            }

            if ($event->getAttribute('status') !== Event::STATUS_PUBLISHED || ! $event->isFinished()) {
                return 'active_event';
            }
        }

        return null;
    }
}

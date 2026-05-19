<?php

namespace App\Services;

use App\Exceptions\EventRequestReviewException;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EventRequestReviewService
{
    public function reject(EventRequest $eventRequest, User $reviewer, ?string $reason): EventRequest
    {
        $this->ensureReviewerIsAdmin($reviewer);

        $reviewedRequest = $this->markReviewed($eventRequest, [
            'status' => EventRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by_id' => $reviewer->getKey(),
        ]);

        NotificationService::eventRequestReviewed($reviewedRequest, EventRequest::STATUS_REJECTED);

        return $reviewedRequest;
    }

    /** @return array{event_request: EventRequest, event: Event} */
    public function approve(EventRequest $eventRequest, User $reviewer): array
    {
        $this->ensureReviewerIsAdmin($reviewer);

        return DB::transaction(function () use ($eventRequest, $reviewer) {
            $reviewedRequest = $this->markReviewed($eventRequest, [
                'status' => EventRequest::STATUS_APPROVED,
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by_id' => $reviewer->getKey(),
            ]);

            $start = $reviewedRequest->getAttribute('preferred_start');
            if (! $start instanceof Carbon) {
                $start = now()->addWeek();
            }

            $end = $reviewedRequest->getAttribute('preferred_end');
            if (! $end instanceof Carbon) {
                $end = $start->copy()->addHours(4);
            }

            $event = Event::create([
                'event_request_id' => $reviewedRequest->getKey(),
                'organizer_id' => null,
                'created_by' => $reviewer->getKey(),
                'title' => $reviewedRequest->getAttribute('title'),
                'description' => $reviewedRequest->getAttribute('description'),
                'image_path' => $reviewedRequest->getAttribute('image_path'),
                'location' => $reviewedRequest->getAttribute('location'),
                'start_at' => $start,
                'end_at' => $end,
                'capacity' => 100,
                'registered_count' => 0,
                'ticket_price' => $reviewedRequest->getAttribute('ticket_price') ?? 0,
                'status' => Event::STATUS_DRAFT,
            ]);

            NotificationService::eventRequestReviewed($reviewedRequest, EventRequest::STATUS_APPROVED);

            return [
                'event_request' => $reviewedRequest,
                'event' => $event->load('eventRequest'),
            ];
        });
    }

    /** @param array<string, mixed> $attributes */
    private function markReviewed(EventRequest $eventRequest, array $attributes): EventRequest
    {
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

    private function ensureReviewerIsAdmin(User $reviewer): void
    {
        if (! $reviewer->isAdmin()) {
            throw new EventRequestReviewException('Accès refusé pour ce rôle.', 403);
        }
    }
}

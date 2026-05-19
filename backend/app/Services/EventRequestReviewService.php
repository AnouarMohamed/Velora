<?php

namespace App\Services;

use App\Exceptions\EventRequestReviewException;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventRequestReviewService
{
    public function reject(EventRequest $eventRequest, User $reviewer, ?string $reason): EventRequest
    {
        $reviewedRequest = $this->markReviewed($eventRequest, [
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_at' => now(),
            'reviewed_by_id' => $reviewer->id,
        ]);

        NotificationService::eventRequestReviewed($reviewedRequest, 'rejected');

        return $reviewedRequest;
    }

    /** @return array{event_request: EventRequest, event: Event} */
    public function approve(EventRequest $eventRequest, User $reviewer): array
    {
        return DB::transaction(function () use ($eventRequest, $reviewer) {
            $reviewedRequest = $this->markReviewed($eventRequest, [
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_at' => now(),
                'reviewed_by_id' => $reviewer->id,
            ]);

            $start = $reviewedRequest->preferred_start ?? now()->addWeek();
            $end = $reviewedRequest->preferred_end ?? $start->copy()->addHours(4);

            $event = Event::create([
                'event_request_id' => $reviewedRequest->id,
                'organizer_id' => null,
                'created_by' => $reviewer->id,
                'title' => $reviewedRequest->title,
                'description' => $reviewedRequest->description,
                'image_path' => $reviewedRequest->image_path,
                'location' => $reviewedRequest->location,
                'start_at' => $start,
                'end_at' => $end,
                'capacity' => 100,
                'registered_count' => 0,
                'ticket_price' => $reviewedRequest->ticket_price ?? 0,
                'status' => 'draft',
            ]);

            NotificationService::eventRequestReviewed($reviewedRequest, 'approved');

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
            ->whereKey($eventRequest->id)
            ->where('status', 'pending')
            ->update($attributes);

        if (! $updated) {
            throw new EventRequestReviewException('Cette demande a déjà été traitée.');
        }

        $eventRequest->refresh();

        return $eventRequest;
    }
}

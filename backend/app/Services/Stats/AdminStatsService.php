<?php

namespace App\Services\Stats;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationStatsService;
use App\Support\Money;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Service providing high-level statistics and dashboard data for Administrators.
 *
 * It aggregates data across users, events, payments, and requests, providing a
 * comprehensive overview of the platform's activity.
 */
class AdminStatsService
{
    /**
     * @param  RegistrationStatsService  $registrationStats  Service to attach registration counts to event models.
     */
    public function __construct(private readonly RegistrationStatsService $registrationStats) {}

    /**
     * Aggregates various metrics into a single payload for the admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'users_total' => User::count(),
            'users_by_role' => $this->usersByRole(),
            'events_total' => Event::count(),
            'events_published' => Event::where('status', Event::STATUS_PUBLISHED)->count(),
            'registrations_total' => Registration::count(),
            // Revenue is calculated from successfully completed payments only.
            'revenue' => Money::floatFromCents(Payment::where('status', 'completed')->sum('amount_cents')),
            'pending_requests' => EventRequest::where('status', EventRequest::STATUS_PENDING)->count(),
            'pending_publications' => Event::where('status', Event::STATUS_PENDING_PUBLICATION)->count(),
            'past_events' => $this->pastEvents(),
        ];
    }

    /**
     * Retrieves a breakdown of user counts grouped by their roles.
     *
     * Uses MongoDB's aggregation framework for efficient counting across the collection.
     *
     * @return array<string, int> Map of role names to user counts.
     */
    private function usersByRole(): array
    {
        /** @var MongoConnection $connection */
        $connection = DB::connection('mongodb');

        $results = $connection
            ->getDatabase()
            ->selectCollection('users')
            ->aggregate([
                ['$group' => ['_id' => '$role', 'count' => ['$sum' => 1]]],
            ]);

        $counts = [];
        foreach ($results as $result) {
            $role = data_get($result, '_id');
            $counts[(string) ($role ?? '')] = (int) data_get($result, 'count', 0);
        }

        return $counts;
    }

    /**
     * Retrieves and formats a list of events that have already finished.
     *
     * @return list<array<string, mixed>>
     */
    private function pastEvents(): array
    {
        $events = Event::query()
            ->where('status', Event::STATUS_PUBLISHED)
            ->finished()
            ->with([
                'organizer:id,name',
                'eventRequest:id,title,contact_name,contact_email',
            ])
            ->get();

        $this->registrationStats->attachCount($events, 'tickets_count', 'paid');

        return $events
            ->sort(fn (Event $a, Event $b): int => $this->comparePastEvents($a, $b))
            ->values()
            ->map(fn (Event $event): array => $this->formatPastEvent($event))
            ->all();
    }

    /**
     * Custom comparison logic to sort events: latest end date first.
     */
    private function comparePastEvents(Event $a, Event $b): int
    {
        $aEffectiveEnd = $this->timestamp($a->getAttribute('end_at') ?? $a->getAttribute('start_at'));
        $bEffectiveEnd = $this->timestamp($b->getAttribute('end_at') ?? $b->getAttribute('start_at'));

        if ($aEffectiveEnd === $bEffectiveEnd) {
            return $this->timestamp($b->getAttribute('start_at')) <=> $this->timestamp($a->getAttribute('start_at'));
        }

        return $bEffectiveEnd <=> $aEffectiveEnd;
    }

    /**
     * Normalizes various date formats into a Unix timestamp.
     */
    private function timestamp(mixed $value): int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? 0 : $timestamp;
    }

    /**
     * Formats an Event model into a serialized array for API responses.
     *
     * @return array<string, mixed>
     */
    private function formatPastEvent(Event $event): array
    {
        $eventRequest = $event->relationLoaded('eventRequest') ? $event->getRelation('eventRequest') : null;
        $organizer = $event->relationLoaded('organizer') ? $event->getRelation('organizer') : null;

        return [
            'id' => $event->getKey(),
            'title' => $event->getAttribute('title'),
            'description' => $event->getAttribute('description'),
            'image_url' => $event->getAttribute('image_url'),
            'location' => $event->getAttribute('location'),
            'start_at' => $event->getAttribute('start_at'),
            'end_at' => $event->getAttribute('end_at'),
            'ticket_price' => (float) $event->getAttribute('ticket_price'),
            'registered_count' => $event->getAttribute('registered_count'),
            'capacity' => $event->getAttribute('capacity'),
            'tickets_count' => (int) $event->getAttribute('tickets_count'),
            'organizer' => $organizer,
            'event_request' => $eventRequest instanceof EventRequest ? [
                'contact_name' => $eventRequest->getAttribute('contact_name'),
                'contact_email' => $eventRequest->getAttribute('contact_email'),
            ] : null,
        ];
    }
}

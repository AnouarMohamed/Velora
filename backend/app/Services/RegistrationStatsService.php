<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Service for aggregating and managing registration statistics.
 *
 * This service leverages MongoDB's aggregation framework to efficiently count
 * registrations across multiple events, optionally filtering by payment status.
 * It is primarily used to hydrate event lists with real-time participation data.
 */
class RegistrationStatsService
{
    /**
     * Retrieves registration counts for a list of event IDs.
     *
     * @param  iterable<mixed>  $eventIds  List of event identifiers.
     * @param  string|null  $paymentStatus  Optional filter (e.g., 'paid', 'pending').
     * @return array<string, int> Map of event ID to registration count.
     */
    public function countsByEvent(iterable $eventIds, ?string $paymentStatus = null): array
    {
        // Normalize event IDs to a clean array of strings.
        $eventIds = collect($eventIds)
            ->filter()
            ->map(fn (mixed $eventId): string => (string) $eventId)
            ->unique()
            ->values()
            ->all();

        if ($eventIds === []) {
            return [];
        }

        // Build the MongoDB match criteria.
        $match = ['event_id' => ['$in' => $eventIds]];

        if ($paymentStatus !== null) {
            $match['payment_status'] = $paymentStatus;
        }

        /** @var MongoConnection $connection */
        $connection = DB::connection('mongodb');

        // Execute MongoDB aggregation for high performance across large datasets.
        // We group by event_id and sum the occurrences.
        $rows = $connection
            ->getDatabase()
            ->selectCollection((new Registration)->getTable())
            ->aggregate([
                ['$match' => $match],
                ['$group' => ['_id' => '$event_id', 'count' => ['$sum' => 1]]],
            ]);

        // Transform the raw MongoDB cursor into a PHP associative array.
        return collect(iterator_to_array($rows))
            ->mapWithKeys(fn (mixed $row): array => [
                (string) data_get($row, '_id') => (int) data_get($row, 'count', 0),
            ])
            ->all();
    }

    /**
     * Attaches registration counts to a collection of event models as a dynamic attribute.
     *
     * @param  Collection<int, mixed>  $events  The collection of Event models to hydrate.
     * @param  string  $attribute  The name of the virtual attribute to set (e.g., 'paid_registrations_count').
     * @param  string|null  $paymentStatus  Optional status filter for the counts.
     */
    public function attachCount(Collection $events, string $attribute, ?string $paymentStatus = null): void
    {
        // Fetch all counts in a single query to avoid N+1 issues.
        $counts = $this->countsByEvent($events->pluck('id'), $paymentStatus);

        // Inject the counts into each event instance.
        $events->each(function (mixed $event) use ($attribute, $counts): void {
            $event->setAttribute($attribute, $counts[(string) $event->id] ?? 0);
        });
    }
}

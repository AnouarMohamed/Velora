<?php

namespace App\Services;

use App\Models\Registration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection as MongoConnection;

class RegistrationStatsService
{
    /** @param iterable<mixed> $eventIds @return array<string, int> */
    public function countsByEvent(iterable $eventIds, ?string $paymentStatus = null): array
    {
        $eventIds = collect($eventIds)
            ->filter()
            ->map(fn (mixed $eventId): string => (string) $eventId)
            ->unique()
            ->values()
            ->all();

        if ($eventIds === []) {
            return [];
        }

        $match = ['event_id' => ['$in' => $eventIds]];

        if ($paymentStatus !== null) {
            $match['payment_status'] = $paymentStatus;
        }

        /** @var MongoConnection $connection */
        $connection = DB::connection('mongodb');

        $rows = $connection
            ->getDatabase()
            ->selectCollection((new Registration)->getTable())
            ->aggregate([
                ['$match' => $match],
                ['$group' => ['_id' => '$event_id', 'count' => ['$sum' => 1]]],
            ]);

        return collect(iterator_to_array($rows))
            ->mapWithKeys(fn (mixed $row): array => [
                (string) data_get($row, '_id') => (int) data_get($row, 'count', 0),
            ])
            ->all();
    }

    /** @param Collection<int, mixed> $events */
    public function attachCount(Collection $events, string $attribute, ?string $paymentStatus = null): void
    {
        $counts = $this->countsByEvent($events->pluck('id'), $paymentStatus);

        $events->each(function (mixed $event) use ($attribute, $counts): void {
            $event->setAttribute($attribute, $counts[(string) $event->id] ?? 0);
        });
    }
}

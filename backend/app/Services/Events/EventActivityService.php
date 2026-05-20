<?php

namespace App\Services\Events;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for managing the public-facing program or agenda of an event.
 *
 * Activities represent specific segments of an event (e.g., keynote, coffee break, workshop).
 * This service handles the chronological ordering and management of these activities
 * by authorized organizers and admins.
 */
class EventActivityService
{
    /**
     * Retrieves the full program for an event.
     *
     * @return Collection<int, EventActivity>
     */
    public function list(User $actor, Event $event): Collection
    {
        $this->ensureCanManage($actor, $event);

        /** @var Collection<int, EventActivity> $activities */
        $activities = $event->activities()
            // First sort by manually defined order, then by start time.
            ->orderBy('sort_order', 'asc')
            ->orderBy('starts_at', 'asc')
            ->get();

        return $activities;
    }

    /**
     * Adds a new activity to the event's program.
     *
     * @param  array<string, mixed>  $data  Activity details (title, type, starts_at, ends_at, etc.).
     */
    public function create(User $actor, Event $event, array $data): EventActivity
    {
        $this->ensureCanManage($actor, $event);

        /** @var EventActivity $activity */
        $activity = $event->activities()->create($data + [
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $activity;
    }

    /**
     * Updates an existing activity.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Event $event, EventActivity $activity, array $data): EventActivity
    {
        $this->ensureActivityBelongsToEvent($activity, $event);
        $this->ensureCanManage($actor, $event);

        $activity->update($data);

        return $activity->fresh() ?? $activity;
    }

    /**
     * Removes an activity from the program.
     */
    public function delete(User $actor, Event $event, EventActivity $activity): void
    {
        $this->ensureActivityBelongsToEvent($activity, $event);
        $this->ensureCanManage($actor, $event);

        $activity->delete();
    }

    /**
     * Enforces that only assigned organizers or admins can manage the event program.
     */
    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    /**
     * Validates that an activity actually belongs to the specified event.
     */
    private function ensureActivityBelongsToEvent(EventActivity $activity, Event $event): void
    {
        if ((string) $activity->getAttribute('event_id') !== (string) $event->getKey()) {
            throw new EventManagementException('Not Found', 404);
        }
    }
}

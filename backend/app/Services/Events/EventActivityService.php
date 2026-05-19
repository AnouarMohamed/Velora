<?php

namespace App\Services\Events;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EventActivityService
{
    /** @return Collection<int, EventActivity> */
    public function list(User $actor, Event $event): Collection
    {
        $this->ensureCanManage($actor, $event);

        /** @var Collection<int, EventActivity> $activities */
        $activities = $event->activities()
            ->orderBy('sort_order', 'asc')
            ->orderBy('starts_at', 'asc')
            ->get();

        return $activities;
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, Event $event, array $data): EventActivity
    {
        $this->ensureCanManage($actor, $event);

        /** @var EventActivity $activity */
        $activity = $event->activities()->create($data + [
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return $activity;
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Event $event, EventActivity $activity, array $data): EventActivity
    {
        $this->ensureActivityBelongsToEvent($activity, $event);
        $this->ensureCanManage($actor, $event);

        $activity->update($data);

        return $activity->fresh() ?? $activity;
    }

    public function delete(User $actor, Event $event, EventActivity $activity): void
    {
        $this->ensureActivityBelongsToEvent($activity, $event);
        $this->ensureCanManage($actor, $event);

        $activity->delete();
    }

    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    private function ensureActivityBelongsToEvent(EventActivity $activity, Event $event): void
    {
        if ((string) $activity->getAttribute('event_id') !== (string) $event->getKey()) {
            throw new EventManagementException('Not Found', 404);
        }
    }
}

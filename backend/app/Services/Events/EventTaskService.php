<?php

namespace App\Services\Events;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\EventTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class EventTaskService
{
    /** @return Collection<int, EventTask> */
    public function list(User $actor, Event $event): Collection
    {
        $this->ensureCanManage($actor, $event);

        /** @var Collection<int, EventTask> $tasks */
        $tasks = $event->tasks()
            ->orderBy('due_at', 'asc')
            ->get();

        return $tasks;
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, Event $event, array $data): EventTask
    {
        $this->ensureCanManage($actor, $event);

        /** @var EventTask $task */
        $task = $event->tasks()->create($data + ['is_done' => false]);

        return $task;
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Event $event, EventTask $task, array $data): EventTask
    {
        $this->ensureTaskBelongsToEvent($task, $event);
        $this->ensureCanManage($actor, $event);

        $task->update($data);

        return $task->fresh() ?? $task;
    }

    public function delete(User $actor, Event $event, EventTask $task): void
    {
        $this->ensureTaskBelongsToEvent($task, $event);
        $this->ensureCanManage($actor, $event);

        $task->delete();
    }

    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    private function ensureTaskBelongsToEvent(EventTask $task, Event $event): void
    {
        if ((string) $task->getAttribute('event_id') !== (string) $event->getKey()) {
            throw new EventManagementException('Not Found', 404);
        }
    }
}

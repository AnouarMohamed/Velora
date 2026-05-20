<?php

namespace App\Services\Events;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\EventTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for managing internal planning tasks for events.
 *
 * Tasks are specific to-do items used by organizers and admins to track
 * preparation progress. This service enforces that only authorized
 * personnel can create, view, or modify tasks for a given event.
 */
class EventTaskService
{
    /**
     * Lists all tasks associated with an event.
     *
     * @param  User  $actor  The user requesting the list.
     * @param  Event  $event  The event tasks belong to.
     * @return Collection<int, EventTask>
     */
    public function list(User $actor, Event $event): Collection
    {
        $this->ensureCanManage($actor, $event);

        /** @var Collection<int, EventTask> $tasks */
        $tasks = $event->tasks()
            ->orderBy('due_at', 'asc') // Sort by due date for chronological planning.
            ->get();

        return $tasks;
    }

    /**
     * Creates a new planning task for an event.
     *
     * @param  array<string, mixed>  $data  Task details (title, description, due_at, etc.).
     */
    public function create(User $actor, Event $event, array $data): EventTask
    {
        $this->ensureCanManage($actor, $event);

        // New tasks are always initialized as not done.
        /** @var EventTask $task */
        $task = $event->tasks()->create($data + ['is_done' => false]);

        return $task;
    }

    /**
     * Updates an existing task's details or completion status.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Event $event, EventTask $task, array $data): EventTask
    {
        $this->ensureTaskBelongsToEvent($task, $event);
        $this->ensureCanManage($actor, $event);

        $task->update($data);

        return $task->fresh() ?? $task;
    }

    /**
     * Permanently removes a task.
     */
    public function delete(User $actor, Event $event, EventTask $task): void
    {
        $this->ensureTaskBelongsToEvent($task, $event);
        $this->ensureCanManage($actor, $event);

        $task->delete();
    }

    /**
     * Enforces that only assigned organizers or admins can manage tasks.
     */
    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    /**
     * Validates that a task actually belongs to the specified event to prevent cross-event manipulation.
     */
    private function ensureTaskBelongsToEvent(EventTask $task, Event $event): void
    {
        if ((string) $task->getAttribute('event_id') !== (string) $event->getKey()) {
            throw new EventManagementException('Not Found', 404);
        }
    }
}

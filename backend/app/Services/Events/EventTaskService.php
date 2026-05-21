<?php

namespace App\Services\Events;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\EventTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service pour la gestion des tâches de planification interne pour les événements.
 *
 * Les tâches sont des éléments à faire spécifiques utilisés par les organisateurs et les administrateurs pour suivre
 * les progrès de la préparation. Ce service impose que seul le personnel autorisé
 * puisse créer, visualiser ou modifier les tâches pour un événement donné.
 */
class EventTaskService
{
    /**
     * Liste toutes les tâches associées à un événement.
     *
     * @param  User  $actor  L'utilisateur demandant la liste.
     * @param  Event  $event  L'événement auquel appartiennent les tâches.
     * @return Collection<int, EventTask>
     */
    public function list(User $actor, Event $event): Collection
    {
        $this->ensureCanManage($actor, $event);

        /** @var Collection<int, EventTask> $tasks */
        $tasks = $event->tasks()
            ->orderBy('due_at', 'asc') // Trier par date d'échéance pour une planification chronologique.
            ->get();

        return $tasks;
    }

    /**
     * Crée une nouvelle tâche de planification pour un événement.
     *
     * @param  array<string, mixed>  $data  Détails de la tâche (titre, description, due_at, etc.).
     */
    public function create(User $actor, Event $event, array $data): EventTask
    {
        $this->ensureCanManage($actor, $event);

        // Les nouvelles tâches sont toujours initialisées comme non terminées.
        /** @var EventTask $task */
        $task = $event->tasks()->create($data + ['is_done' => false]);

        return $task;
    }

    /**
     * Met à jour les détails d'une tâche existante ou son statut d'achèvement.
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
     * Supprime définitivement une tâche.
     */
    public function delete(User $actor, Event $event, EventTask $task): void
    {
        $this->ensureTaskBelongsToEvent($task, $event);
        $this->ensureCanManage($actor, $event);

        $task->delete();
    }

    /**
     * Impose que seuls les organisateurs assignés ou les administrateurs puissent gérer les tâches.
     */
    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    /**
     * Valide qu'une tâche appartient réellement à l'événement spécifié pour empêcher la manipulation entre événements.
     */
    private function ensureTaskBelongsToEvent(EventTask $task, Event $event): void
    {
        if ($this->stringValue($task->getAttribute('event_id')) !== $this->stringValue($event->getKey())) {
            throw new EventManagementException('Not Found', 404);
        }
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

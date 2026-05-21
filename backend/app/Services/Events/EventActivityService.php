<?php

namespace App\Services\Events;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service pour la gestion du programme ou de l'agenda public d'un événement.
 *
 * Les activités représentent des segments spécifiques d'un événement (ex: discours d'ouverture, pause café, atelier).
 * Ce service gère l'ordre chronologique et la gestion de ces activités
 * par les organisateurs et administrateurs autorisés.
 */
class EventActivityService
{
    /**
     * Récupère le programme complet d'un événement.
     *
     * @return Collection<int, EventActivity>
     */
    public function list(User $actor, Event $event): Collection
    {
        $this->ensureCanManage($actor, $event);

        /** @var Collection<int, EventActivity> $activities */
        $activities = $event->activities()
            // Trier d'abord par l'ordre défini manuellement, puis par l'heure de début.
            ->orderBy('sort_order', 'asc')
            ->orderBy('starts_at', 'asc')
            ->get();

        return $activities;
    }

    /**
     * Ajoute une nouvelle activité au programme de l'événement.
     *
     * @param  array<string, mixed>  $data  Détails de l'activité (titre, type, starts_at, ends_at, etc.).
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
     * Met à jour une activité existante.
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
     * Supprime une activité du programme.
     */
    public function delete(User $actor, Event $event, EventActivity $activity): void
    {
        $this->ensureActivityBelongsToEvent($activity, $event);
        $this->ensureCanManage($actor, $event);

        $activity->delete();
    }

    /**
     * Impose que seuls les organisateurs assignés ou les administrateurs puissent gérer le programme de l'événement.
     */
    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    /**
     * Valide qu'une activité appartient réellement à l'événement spécifié.
     */
    private function ensureActivityBelongsToEvent(EventActivity $activity, Event $event): void
    {
        if ($this->stringValue($activity->getAttribute('event_id')) !== $this->stringValue($event->getKey())) {
            throw new EventManagementException('Not Found', 404);
        }
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

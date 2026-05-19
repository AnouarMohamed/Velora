<?php

namespace App\Services;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\User;

class EventManagementService
{
    public function __construct(private readonly EventImageStorage $images) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Event
    {
        $imagePath = $this->images->storeBase64(
            $data['image_data'] ?? null,
            $data['image_mime'] ?? null,
        );

        $status = $this->statusForCreate($actor, $data['status'] ?? Event::STATUS_DRAFT);

        $event = Event::create([
            'event_request_id' => null,
            'organizer_id' => $actor->id,
            'created_by' => $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'image_path' => $imagePath,
            'location' => $data['location'] ?? null,
            'room' => $data['room'] ?? null,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'capacity' => $data['capacity'],
            'registered_count' => 0,
            'ticket_price' => $data['ticket_price'] ?? 0,
            'status' => $status,
        ]);

        NotificationService::organizerEventCreated($event, $actor);

        if ($status === Event::STATUS_PUBLISHED) {
            NotificationService::eventPublished($event);
        }

        return $event;
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Event $event, array $data): Event
    {
        $this->ensureCanManage($actor, $event);

        $data = $this->dataAllowedForActor($actor, $data);
        $this->ensureCapacityCanHoldRegistrations($event, $data['capacity'] ?? null);

        $wasPublished = $event->status === Event::STATUS_PUBLISHED;
        $previousStatus = $event->status;

        $event->update($data);
        $event->refresh();

        if ($actor->isAdmin() && NotificationService::organizerIdsForEvent($event) !== []) {
            NotificationService::eventUpdatedByAdmin($event);
        }

        if (! $wasPublished && $event->status === Event::STATUS_PUBLISHED) {
            if ($previousStatus === Event::STATUS_PENDING_PUBLICATION) {
                NotificationService::publicationApproved($event);
            } else {
                NotificationService::eventPublished($event);
            }
        }

        return $event;
    }

    public function updateCapacity(User $actor, Event $event, int $capacity): Event
    {
        $this->ensureCanManage($actor, $event);
        $this->ensureCapacityCanHoldRegistrations($event, $capacity);

        $event->update(['capacity' => $capacity]);
        $event->refresh();

        return $event;
    }

    public function assignOrganizer(Event $event, string $organizerId): Event
    {
        $organizer = User::query()
            ->whereKey($organizerId)
            ->whereIn('role', [User::ROLE_ORGANIZER, User::ROLE_ADMIN])
            ->firstOrFail();

        $event->update(['organizer_id' => $organizer->id]);
        $event->refresh();

        if ($organizer->role === User::ROLE_ORGANIZER) {
            NotificationService::eventAssigned($event, $organizer);
        }

        return $event->load('organizer');
    }

    public function requestPublication(User $actor, Event $event): Event
    {
        $this->ensureCanManage($actor, $event);

        if ($actor->isAdmin()) {
            throw new EventManagementException('Publiez directement depuis l’espace administrateur.');
        }

        if (! in_array($event->status, [Event::STATUS_DRAFT, Event::STATUS_PENDING_PUBLICATION], true)) {
            throw new EventManagementException('Cet événement ne peut pas être soumis à publication.');
        }

        $event->update(['status' => Event::STATUS_PENDING_PUBLICATION]);
        $event->refresh();

        NotificationService::publicationRequested($event, $actor);

        return $event;
    }

    public function approvePublication(User $actor, Event $event): Event
    {
        if (! $actor->isAdmin()) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }

        if ($event->status !== Event::STATUS_PENDING_PUBLICATION) {
            throw new EventManagementException('Aucune demande de publication en attente pour cet événement.');
        }

        $event->update(['status' => Event::STATUS_PUBLISHED]);
        $event->refresh();

        NotificationService::publicationApproved($event);

        return $event;
    }

    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    private function ensureCapacityCanHoldRegistrations(Event $event, mixed $capacity): void
    {
        if ($capacity !== null && (int) $capacity < (int) $event->registered_count) {
            throw new EventManagementException('La capacité ne peut pas être inférieure au nombre d’inscrits.');
        }
    }

    private function statusForCreate(User $actor, string $requestedStatus): string
    {
        if ($actor->isAdmin()) {
            return $requestedStatus;
        }

        return $requestedStatus === Event::STATUS_PENDING_PUBLICATION
            ? Event::STATUS_PENDING_PUBLICATION
            : Event::STATUS_DRAFT;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function dataAllowedForActor(User $actor, array $data): array
    {
        if (! isset($data['status']) || $actor->isAdmin()) {
            return $data;
        }

        if ($data['status'] === Event::STATUS_PUBLISHED) {
            throw new EventManagementException('Seul un administrateur peut publier l’événement. Envoyez une demande de publication.');
        }

        if (! in_array($data['status'], [
            Event::STATUS_DRAFT,
            Event::STATUS_PENDING_PUBLICATION,
            Event::STATUS_CANCELLED,
        ], true)) {
            unset($data['status']);
        }

        return $data;
    }
}

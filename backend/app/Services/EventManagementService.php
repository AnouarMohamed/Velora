<?php

namespace App\Services;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\User;

/**
 * Service for managing the core Event lifecycle.
 *
 * This service handles creation, updates, capacity management, organizer assignment,
 * and the publication workflow (draft -> pending -> published).
 * It enforces strict business rules regarding user roles and event status transitions.
 */
class EventManagementService
{
    /**
     * @param  EventImageStorage  $images  Service for handling event image uploads.
     */
    public function __construct(private readonly EventImageStorage $images) {}

    /**
     * Creates a new event manually (usually by an Organizer or Admin).
     *
     * @param  User  $actor  The user creating the event.
     * @param  array<string, mixed>  $data  Event attributes.
     */
    public function create(User $actor, array $data): Event
    {
        // Handle image upload if provided.
        $imagePath = $this->images->storeBase64(
            $data['image_data'] ?? null,
            $data['image_mime'] ?? null,
        );

        // Determine initial status based on actor's role.
        $status = $this->statusForCreate($actor, $data['status'] ?? Event::STATUS_DRAFT);

        $event = Event::create([
            'event_request_id' => null, // Direct creation doesn't have a source request.
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

        // Notify admins if an organizer created this.
        NotificationService::organizerEventCreated($event, $actor);

        // If an admin created and published it immediately, notify participants.
        if ($status === Event::STATUS_PUBLISHED) {
            NotificationService::eventPublished($event);
        }

        return $event;
    }

    /**
     * Updates an existing event.
     *
     * @param  User  $actor  The user performing the update.
     * @param  Event  $event  The event to update.
     * @param  array<string, mixed>  $data  Updated attributes.
     *
     * @throws EventManagementException If authorization or business rule fails.
     */
    public function update(User $actor, Event $event, array $data): Event
    {
        // Enforce ownership or admin rights.
        $this->ensureCanManage($actor, $event);

        // Filter data based on what the actor is allowed to change (e.g., status restrictions for organizers).
        $data = $this->dataAllowedForActor($actor, $data);

        // Prevent lowering capacity below the current number of participants.
        $this->ensureCapacityCanHoldRegistrations($event, $data['capacity'] ?? null);

        $wasPublished = $event->status === Event::STATUS_PUBLISHED;
        $previousStatus = $event->status;

        $event->update($data);
        $event->refresh();

        // If an admin made changes, notify the assigned organizer.
        if ($actor->isAdmin() && NotificationService::organizerIdsForEvent($event) !== []) {
            NotificationService::eventUpdatedByAdmin($event);
        }

        // Handle notifications for status transitions to 'published'.
        if (! $wasPublished && $event->status === Event::STATUS_PUBLISHED) {
            if ($previousStatus === Event::STATUS_PENDING_PUBLICATION) {
                NotificationService::publicationApproved($event);
            } else {
                NotificationService::eventPublished($event);
            }
        }

        return $event;
    }

    /**
     * Specifically updates the event capacity.
     */
    public function updateCapacity(User $actor, Event $event, int $capacity): Event
    {
        $this->ensureCanManage($actor, $event);
        $this->ensureCapacityCanHoldRegistrations($event, $capacity);

        $event->update(['capacity' => $capacity]);
        $event->refresh();

        return $event;
    }

    /**
     * Assigns a specific user as the lead organizer for an event.
     */
    public function assignOrganizer(Event $event, string $organizerId): Event
    {
        $organizer = User::query()
            ->whereKey($organizerId)
            ->whereIn('role', [User::ROLE_ORGANIZER, User::ROLE_ADMIN])
            ->firstOrFail();

        $event->update(['organizer_id' => $organizer->id]);
        $event->refresh();

        // Notify the organizer about their new assignment.
        if ($organizer->role === User::ROLE_ORGANIZER) {
            NotificationService::eventAssigned($event, $organizer);
        }

        return $event->load('organizer');
    }

    /**
     * Transition an event from 'draft' to 'pending_publication'.
     *
     * @throws EventManagementException
     */
    public function requestPublication(User $actor, Event $event): Event
    {
        $this->ensureCanManage($actor, $event);

        if ($actor->isAdmin()) {
            throw new EventManagementException('Publiez directement depuis l’espace administrateur.');
        }

        // Only drafts or already pending events can be (re)submitted.
        if (! in_array($event->status, [Event::STATUS_DRAFT, Event::STATUS_PENDING_PUBLICATION], true)) {
            throw new EventManagementException('Cet événement ne peut pas être soumis à publication.');
        }

        $event->update(['status' => Event::STATUS_PENDING_PUBLICATION]);
        $event->refresh();

        // Notify admins that an event is ready for review.
        NotificationService::publicationRequested($event, $actor);

        return $event;
    }

    /**
     * Approves a publication request, making the event live.
     *
     * @throws EventManagementException
     */
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

        // Notify organizers and participants that the event is live.
        NotificationService::publicationApproved($event);

        return $event;
    }

    /**
     * Enforces that only assigned organizers or admins can modify an event.
     */
    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    /**
     * Validates that new capacity is sufficient for current registrations.
     */
    private function ensureCapacityCanHoldRegistrations(Event $event, mixed $capacity): void
    {
        if ($capacity !== null && (int) $capacity < (int) $event->registered_count) {
            throw new EventManagementException('La capacité ne peut pas être inférieure au nombre d’inscrits.');
        }
    }

    /**
     * Determines the initial status based on role. Admins can bypass workflows.
     */
    private function statusForCreate(User $actor, string $requestedStatus): string
    {
        if ($actor->isAdmin()) {
            return $requestedStatus;
        }

        // Organizers cannot publish directly.
        return $requestedStatus === Event::STATUS_PENDING_PUBLICATION
            ? Event::STATUS_PENDING_PUBLICATION
            : Event::STATUS_DRAFT;
    }

    /**
     * Sanitizes and restricts attributes based on user role.
     */
    private function dataAllowedForActor(User $actor, array $data): array
    {
        if (! isset($data['status']) || $actor->isAdmin()) {
            return $data;
        }

        // Organizers cannot set status to 'published' via standard update.
        if ($data['status'] === Event::STATUS_PUBLISHED) {
            throw new EventManagementException('Seul un administrateur peut publier l’événement. Envoyez une demande de publication.');
        }

        // Validate allowed status transitions for organizers.
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

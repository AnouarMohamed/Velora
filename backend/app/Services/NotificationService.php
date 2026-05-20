<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Feedback;
use App\Models\Registration;
use App\Models\User;

/**
 * Service responsible for managing and dispatching application-level notifications.
 *
 * This service centralizes all notification logic, ensuring consistent messaging
 * across the platform for different user roles (Admins, Organizers, Clients, and Participants).
 * It handles both internal system notifications and external-facing updates.
 */
class NotificationService
{
    /**
     * Dispatches a notification to one or multiple users.
     *
     * @param  string|int|array  $userIds  Single user ID or an array of user IDs to notify.
     * @param  string  $type  The category/type of notification (e.g., 'admin_user_registered').
     * @param  string  $title  The short, descriptive title of the notification.
     * @param  string  $message  The main content body of the notification.
     * @param  array  $data  Optional metadata (e.g., links, resource IDs) for frontend navigation or context.
     */
    public static function send(
        string|int|array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = [],
    ): void {
        // Ensure we have a unique list of valid user IDs to prevent duplicate notifications.
        $ids = array_unique(array_filter(is_array($userIds) ? $userIds : [$userIds]));

        foreach ($ids as $userId) {
            AppNotification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data ?: null,
            ]);
        }
    }

    /**
     * Retrieves all user IDs with the Administrator role.
     *
     * @return list<string>
     */
    public static function adminIds(): array
    {
        return User::query()->where('role', User::ROLE_ADMIN)->pluck('id')->all();
    }

    /**
     * Retrieves all user IDs with the Participant role.
     * Used for broadcasting new events or general announcements.
     *
     * @return list<string>
     */
    public static function participantIds(): array
    {
        return User::query()->where('role', User::ROLE_PARTICIPANT)->pluck('id')->all();
    }

    /**
     * Identifies the Client user associated with a specific event request.
     *
     * @param  EventRequest  $request  The request to find the client for.
     */
    public static function clientUserForRequest(EventRequest $request): ?User
    {
        // Clients are matched by their contact email provided in the request.
        return User::query()
            ->where('email', $request->contact_email)
            ->where('role', User::ROLE_CLIENT)
            ->first();
    }

    /**
     * Identifies the Client user associated with an existing Event.
     *
     * @param  Event  $event  The event to find the client for.
     */
    public static function clientUserForEvent(Event $event): ?User
    {
        $event->loadMissing('eventRequest');
        if (! $event->eventRequest) {
            return null;
        }

        return self::clientUserForRequest($event->eventRequest);
    }

    /**
     * Gathers all relevant Organizer IDs for an event, including both the
     * assigned organizer and the original creator if they are an organizer.
     *
     * @return list<string> Unique list of organizer user IDs.
     */
    public static function organizerIdsForEvent(Event $event): array
    {
        $event->loadMissing(['organizer', 'creator']);
        $ids = [];

        // Add the currently assigned organizer if valid.
        if ($event->organizer_id && $event->organizer?->role === User::ROLE_ORGANIZER) {
            $ids[] = $event->organizer_id;
        }

        // Add the creator if they are also an organizer and different from the assigned one.
        if (
            $event->created_by
            && $event->created_by !== $event->organizer_id
            && $event->creator?->role === User::ROLE_ORGANIZER
        ) {
            $ids[] = $event->created_by;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Notifies admins when a new user joins the platform.
     *
     * @param  User  $user  The newly registered user.
     */
    public static function userRegistered(User $user): void
    {
        self::send(
            self::adminIds(),
            'admin_user_registered',
            'Nouvel utilisateur',
            sprintf('%s (%s) vient de s’inscrire en tant que %s.', $user->name, $user->email, $user->role),
            ['user_id' => $user->id, 'link' => '/admin/users'],
        );
    }

    /**
     * Notifies admins when a new event request is submitted by a client.
     */
    public static function eventRequestSubmitted(EventRequest $request): void
    {
        self::send(
            self::adminIds(),
            'admin_event_request_pending',
            'Demande d’événement à valider',
            sprintf('Nouvelle demande : « %s ».', $request->title),
            ['event_request_id' => $request->id, 'link' => '/admin/requests'],
        );
    }

    /**
     * Notifies the client about the outcome (approval/rejection) of their event request.
     *
     * @param  string  $decision  Either 'approved' or 'rejected'.
     */
    public static function eventRequestReviewed(EventRequest $request, string $decision): void
    {
        $client = self::clientUserForRequest($request);
        if (! $client) {
            return;
        }

        if ($decision === 'approved') {
            self::send(
                $client->id,
                'client_request_approved',
                'Demande acceptée',
                sprintf('Votre demande « %s » a été acceptée.', $request->title),
                ['event_request_id' => $request->id, 'link' => '/client/stats'],
            );
        } else {
            self::send(
                $client->id,
                'client_request_rejected',
                'Demande refusée',
                sprintf('Votre demande « %s » a été refusée.', $request->title),
                ['event_request_id' => $request->id, 'link' => '/client/stats'],
            );
        }
    }

    /**
     * Notifies admins when an organizer creates a new event manually.
     */
    public static function organizerEventCreated(Event $event, User $creator): void
    {
        if ($creator->role !== User::ROLE_ORGANIZER) {
            return;
        }

        self::send(
            self::adminIds(),
            'admin_organizer_event_created',
            'Événement créé par un organisateur',
            sprintf('%s a créé l’événement « %s ».', $creator->name, $event->title),
            ['event_id' => $event->id, 'link' => '/admin/organizer-events'],
        );
    }

    /**
     * Notifies admins that an event is ready for publication and needs approval.
     */
    public static function publicationRequested(Event $event, User $requester): void
    {
        self::send(
            self::adminIds(),
            'admin_publication_requested',
            'Publication à approuver',
            sprintf('%s demande la publication de « %s ».', $requester->name, $event->title),
            ['event_id' => $event->id, 'link' => '/admin/events'],
        );
    }

    /**
     * Notifies an organizer when they have been assigned to manage an event.
     */
    public static function eventAssigned(Event $event, User $organizer): void
    {
        self::send(
            $organizer->id,
            'organizer_event_assigned',
            'Événement assigné',
            sprintf('L’administrateur vous a assigné l’événement « %s ».', $event->title),
            ['event_id' => $event->id, 'link' => '/organizer/events/'.$event->id],
        );
    }

    /**
     * Notifies the assigned organizers when an admin modifies event details.
     */
    public static function eventUpdatedByAdmin(Event $event): void
    {
        $organizerIds = self::organizerIdsForEvent($event);
        if ($organizerIds === []) {
            return;
        }

        self::send(
            $organizerIds,
            'organizer_event_updated',
            'Événement modifié',
            sprintf('L’administrateur a modifié « %s ».', $event->title),
            ['event_id' => $event->id, 'link' => '/organizer/events/'.$event->id],
        );
    }

    /**
     * Handles notifications when an admin approves an event for publication.
     */
    public static function publicationApproved(Event $event): void
    {
        $organizerIds = self::organizerIdsForEvent($event);
        if ($organizerIds !== []) {
            self::send(
                $organizerIds,
                'organizer_publication_approved',
                'Publication approuvée',
                sprintf('« %s » est maintenant publié en ligne.', $event->title),
                ['event_id' => $event->id, 'link' => '/events/'.$event->id],
            );
        }

        // Trigger broad broadcast and client notification.
        self::eventPublished($event);
    }

    /**
     * Broadcasts that an event is now live to all participants and the original client.
     */
    public static function eventPublished(Event $event): void
    {
        // Mass notification to all potential participants.
        self::send(
            self::participantIds(),
            'participant_new_event',
            'Nouvel événement',
            sprintf('« %s » est disponible à l’inscription.', $event->title),
            ['event_id' => $event->id, 'link' => '/events/'.$event->id],
        );

        // Notify the client who originally requested the event.
        $client = self::clientUserForEvent($event);
        if ($client) {
            self::send(
                $client->id,
                'client_event_published',
                'Événement publié',
                sprintf('Votre événement « %s » est maintenant en ligne.', $event->title),
                ['event_id' => $event->id, 'link' => '/client/stats'],
            );
        }
    }

    /**
     * Notifies admins and organizers when a participant registers for an event.
     */
    public static function participantRegistered(Registration $registration): void
    {
        $registration->loadMissing(['event', 'user']);
        $event = $registration->event;
        // Only notify for live events to avoid noise during setup or draft phases.
        if (! $event || $event->status !== 'published') {
            return;
        }

        $participant = $registration->relationLoaded('user') ? $registration->getRelation('user') : null;
        $name = $participant instanceof User ? $participant->getAttribute('name') : 'Un participant';

        self::send(
            self::adminIds(),
            'admin_participant_registered',
            'Nouvelle inscription',
            sprintf('%s s’est inscrit à « %s ».', $name, $event->title),
            ['event_id' => $event->id, 'registration_id' => $registration->id, 'link' => '/admin/registrations'],
        );

        self::send(
            self::organizerIdsForEvent($event),
            'organizer_participant_registered',
            'Nouvelle inscription',
            sprintf('%s s’est inscrit à « %s ».', $name, $event->title),
            ['event_id' => $event->id, 'link' => '/organizer/registrations'],
        );
    }

    /**
     * Notifies admins and organizers when a participant completes payment for their registration.
     */
    public static function participantPaid(Registration $registration): void
    {
        $registration->loadMissing(['event', 'user']);
        $event = $registration->event;
        if (! $event || $event->status !== 'published') {
            return;
        }

        $participant = $registration->relationLoaded('user') ? $registration->getRelation('user') : null;
        $name = $participant instanceof User ? $participant->getAttribute('name') : 'Un participant';

        self::send(
            self::adminIds(),
            'admin_participant_paid',
            'Paiement reçu',
            sprintf('%s a payé son billet pour « %s ».', $name, $event->title),
            ['event_id' => $event->id, 'registration_id' => $registration->id, 'link' => '/admin/registrations'],
        );

        self::send(
            self::organizerIdsForEvent($event),
            'organizer_participant_paid',
            'Billet payé',
            sprintf('%s a payé pour « %s ».', $name, $event->title),
            ['event_id' => $event->id, 'link' => '/organizer/registrations'],
        );
    }

    /**
     * Notifies admins and organizers when a participant submits feedback for an event.
     * Feedback usually requires moderation before public visibility.
     */
    public static function feedbackSubmitted(Feedback $feedback): void
    {
        $feedback->loadMissing(['event', 'user']);
        $event = $feedback->event;
        if (! $event || $event->status !== 'published') {
            return;
        }

        $feedbackAuthor = $feedback->relationLoaded('user') ? $feedback->getRelation('user') : null;
        $author = $feedbackAuthor instanceof User ? $feedbackAuthor->getAttribute('name') : 'Un participant';

        self::send(
            self::adminIds(),
            'admin_feedback_received',
            'Nouvel avis',
            sprintf('%s a laissé un avis sur « %s » (en attente de validation).', $author, $event->title),
            ['event_id' => $event->id, 'feedback_id' => $feedback->id, 'link' => '/events/'.$event->id],
        );

        self::send(
            self::organizerIdsForEvent($event),
            'organizer_feedback_received',
            'Nouvel avis',
            sprintf('%s a laissé un avis sur « %s ».', $author, $event->title),
            ['event_id' => $event->id, 'link' => '/organizer/events/'.$event->id],
        );
    }

    /**
     * Notifies the author and the event's client when a feedback is approved and published.
     */
    public static function feedbackApproved(Feedback $feedback): void
    {
        $feedback->loadMissing(['event', 'user']);
        $event = $feedback->event;

        // Notify the author that their feedback is now live.
        if ($feedback->user_id) {
            self::send(
                $feedback->user_id,
                'participant_feedback_approved',
                'Avis publié',
                sprintf('Votre avis sur « %s » a été publié.', $event instanceof Event ? $event->getAttribute('title') : 'l’événement'),
                ['event_id' => $event?->id, 'link' => $event ? '/events/'.$event->id : '/my-registrations'],
            );
        }

        // Notify the client about new public feedback on their event.
        if ($event && $event->status === 'published') {
            $client = self::clientUserForEvent($event);
            // Don't notify the client if they are the one who wrote the feedback (unlikely but possible).
            if ($client && $client->id !== $feedback->user_id) {
                $feedbackAuthor = $feedback->relationLoaded('user') ? $feedback->getRelation('user') : null;
                $author = $feedbackAuthor instanceof User ? $feedbackAuthor->getAttribute('name') : 'Un participant';
                self::send(
                    $client->id,
                    'client_feedback_on_event',
                    'Nouveau commentaire',
                    sprintf('%s a publié un avis sur « %s ».', $author, $event->title),
                    ['event_id' => $event->id, 'link' => '/client/stats'],
                );
            }
        }
    }
}

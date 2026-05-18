<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Feedback;
use App\Models\AppNotification;
use App\Models\Registration;
use App\Models\User;

class NotificationService
{
    public static function send(
        int|array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = [],
    ): void {
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

    /** @return list<int> */
    public static function adminIds(): array
    {
        return User::query()->where('role', User::ROLE_ADMIN)->pluck('id')->all();
    }

    /** @return list<int> */
    public static function participantIds(): array
    {
        return User::query()->where('role', User::ROLE_PARTICIPANT)->pluck('id')->all();
    }

    public static function clientUserForRequest(EventRequest $request): ?User
    {
        return User::query()
            ->where('email', $request->contact_email)
            ->where('role', User::ROLE_CLIENT)
            ->first();
    }

    public static function clientUserForEvent(Event $event): ?User
    {
        $event->loadMissing('eventRequest');
        if (! $event->eventRequest) {
            return null;
        }

        return self::clientUserForRequest($event->eventRequest);
    }

    /** @return list<int> */
    public static function organizerIdsForEvent(Event $event): array
    {
        $event->loadMissing(['organizer', 'creator']);
        $ids = [];

        if ($event->organizer_id && $event->organizer?->role === User::ROLE_ORGANIZER) {
            $ids[] = $event->organizer_id;
        }

        if (
            $event->created_by
            && $event->created_by !== $event->organizer_id
            && $event->creator?->role === User::ROLE_ORGANIZER
        ) {
            $ids[] = $event->created_by;
        }

        return array_values(array_unique($ids));
    }

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

        self::eventPublished($event);
    }

    public static function eventPublished(Event $event): void
    {
        self::send(
            self::participantIds(),
            'participant_new_event',
            'Nouvel événement',
            sprintf('« %s » est disponible à l’inscription.', $event->title),
            ['event_id' => $event->id, 'link' => '/events/'.$event->id],
        );

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

    public static function participantRegistered(Registration $registration): void
    {
        $registration->loadMissing(['event', 'user']);
        $event = $registration->event;
        if (! $event || $event->status !== 'published') {
            return;
        }

        $participant = $registration->user;
        $name = $participant?->name ?? 'Un participant';

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

    public static function participantPaid(Registration $registration): void
    {
        $registration->loadMissing(['event', 'user']);
        $event = $registration->event;
        if (! $event || $event->status !== 'published') {
            return;
        }

        $participant = $registration->user;
        $name = $participant?->name ?? 'Un participant';

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

    public static function feedbackSubmitted(Feedback $feedback): void
    {
        $feedback->loadMissing(['event', 'user']);
        $event = $feedback->event;
        if (! $event || $event->status !== 'published') {
            return;
        }

        $author = $feedback->user?->name ?? 'Un participant';

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

    public static function feedbackApproved(Feedback $feedback): void
    {
        $feedback->loadMissing(['event', 'user']);
        $event = $feedback->event;

        if ($feedback->user_id) {
            self::send(
                $feedback->user_id,
                'participant_feedback_approved',
                'Avis publié',
                sprintf('Votre avis sur « %s » a été publié.', $event?->title ?? 'l’événement'),
                ['event_id' => $event?->id, 'link' => $event ? '/events/'.$event->id : '/my-registrations'],
            );
        }

        if ($event && $event->status === 'published') {
            $client = self::clientUserForEvent($event);
            if ($client && $client->id !== $feedback->user_id) {
                $author = $feedback->user?->name ?? 'Un participant';
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

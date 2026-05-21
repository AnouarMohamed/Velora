<?php

namespace App\Services;

use App\Jobs\FanOutPublishedEventNotifications;
use App\Models\AppNotification;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Feedback;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Service responsable de la gestion et de l'envoi des notifications au niveau de l'application.
 *
 * Ce service centralise toute la logique de notification, garantissant une messagerie cohérente
 * sur toute la plateforme pour les différents rôles d'utilisateurs (Administrateurs, Organisateurs, Clients et Participants).
 * Il gère à la fois les notifications internes au système et les mises à jour destinées à l'extérieur.
 */
class NotificationService
{
    /**
     * Envoie une notification à un ou plusieurs utilisateurs.
     *
     * @param  string|int|list<string|int>  $userIds  Un seul ID d'utilisateur ou un tableau d'IDs d'utilisateurs à notifier.
     * @param  string  $type  La catégorie/le type de notification (ex: 'admin_user_registered').
     * @param  string  $title  Le titre court et descriptif de la notification.
     * @param  string  $message  Le corps du contenu principal de la notification.
     * @param  array<string, mixed>  $data  Métadonnées optionnelles (ex: liens, IDs de ressources) pour la navigation frontend ou le contexte.
     */
    public static function send(
        string|int|array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = [],
    ): void {
        $ids = [];
        foreach (is_array($userIds) ? $userIds : [$userIds] as $userId) {
            $ids[] = (string) $userId;
        }
        $ids = array_values(array_unique($ids));

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
     * Récupère tous les IDs d'utilisateurs ayant le rôle d'Administrateur.
     *
     * @return list<string>
     */
    public static function adminIds(): array
    {
        $ids = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_int($id) || is_string($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        /** @var list<string> $ids */
        return $ids;
    }

    /**
     * Identifie l'utilisateur Client associé à une demande d'événement spécifique.
     *
     * @param  EventRequest  $request  La demande pour laquelle trouver le client.
     */
    public static function clientUserForRequest(EventRequest $request): ?User
    {
        // Les clients sont identifiés par leur e-mail de contact fourni dans la demande.
        return User::query()
            ->where('email', self::stringValue($request->getAttribute('contact_email')))
            ->where('role', User::ROLE_CLIENT)
            ->first();
    }

    /**
     * Identifie l'utilisateur Client associé à un événement existant.
     *
     * @param  Event  $event  L'événement pour lequel trouver le client.
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
     * Rassemble tous les IDs d'Organisateurs concernés par un événement, y compris
     * l'organisateur assigné et le créateur original s'il est un organisateur.
     *
     * @return list<string> Liste unique d'IDs d'utilisateurs organisateurs.
     */
    public static function organizerIdsForEvent(Event $event): array
    {
        $event->loadMissing(['organizer', 'creator']);
        $ids = [];

        // Ajouter l'organisateur actuellement assigné s'il est valide.
        if ($event->organizer_id && $event->organizer?->role === User::ROLE_ORGANIZER) {
            $ids[] = $event->organizer_id;
        }

        // Ajouter le créateur s'il est également un organisateur et différent de celui assigné.
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
     * Notifie les administrateurs lorsqu'un nouvel utilisateur rejoint la plateforme.
     *
     * @param  User  $user  L'utilisateur nouvellement inscrit.
     */
    public static function userRegistered(User $user): void
    {
        self::send(
            self::adminIds(),
            'admin_user_registered',
            'Nouvel utilisateur',
            sprintf('%s (%s) vient de s’inscrire en tant que %s.', self::stringValue($user->getAttribute('name')), self::stringValue($user->getAttribute('email')), self::stringValue($user->getAttribute('role'))),
            ['user_id' => self::modelId($user), 'link' => '/admin/users'],
        );
    }

    /**
     * Notifie les administrateurs lorsqu'une nouvelle demande d'événement est soumise par un client.
     */
    public static function eventRequestSubmitted(EventRequest $request): void
    {
        self::send(
            self::adminIds(),
            'admin_event_request_pending',
            'Demande d’événement à valider',
            sprintf('Nouvelle demande : « %s ».', self::stringValue($request->getAttribute('title'))),
            ['event_request_id' => self::modelId($request), 'link' => '/admin/requests'],
        );
    }

    /**
     * Notifie le client du résultat (approbation/rejet) de sa demande d'événement.
     *
     * @param  string  $decision  Soit 'approved' soit 'rejected'.
     */
    public static function eventRequestReviewed(EventRequest $request, string $decision): void
    {
        $client = self::clientUserForRequest($request);
        if (! $client) {
            return;
        }

        if ($decision === 'approved') {
            self::send(
                self::modelId($client),
                'client_request_approved',
                'Demande acceptée',
                sprintf('Votre demande « %s » a été acceptée.', self::stringValue($request->getAttribute('title'))),
                ['event_request_id' => self::modelId($request), 'link' => '/client/stats'],
            );
        } else {
            self::send(
                self::modelId($client),
                'client_request_rejected',
                'Demande refusée',
                sprintf('Votre demande « %s » a été refusée.', self::stringValue($request->getAttribute('title'))),
                ['event_request_id' => self::modelId($request), 'link' => '/client/stats'],
            );
        }
    }

    /**
     * Notifie les administrateurs lorsqu'un organisateur crée manuellement un nouvel événement.
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
            sprintf('%s a créé l’événement « %s ».', self::stringValue($creator->getAttribute('name')), self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'link' => '/admin/organizer-events'],
        );
    }

    /**
     * Notifie les administrateurs qu'un événement est prêt pour la publication et nécessite une approbation.
     */
    public static function publicationRequested(Event $event, User $requester): void
    {
        self::send(
            self::adminIds(),
            'admin_publication_requested',
            'Publication à approuver',
            sprintf('%s demande la publication de « %s ».', self::stringValue($requester->getAttribute('name')), self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'link' => '/admin/events'],
        );
    }

    /**
     * Notifie un organisateur lorsqu'il a été assigné à la gestion d'un événement.
     */
    public static function eventAssigned(Event $event, User $organizer): void
    {
        self::send(
            self::modelId($organizer),
            'organizer_event_assigned',
            'Événement assigné',
            sprintf('L’administrateur vous a assigné l’événement « %s ».', self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'link' => '/organizer/events/'.self::modelId($event)],
        );
    }

    /**
     * Notifie les organisateurs assignés lorsqu'un administrateur modifie les détails d'un événement.
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
            sprintf('L’administrateur a modifié « %s ».', self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'link' => '/organizer/events/'.self::modelId($event)],
        );
    }

    /**
     * Gère les notifications lorsqu'un administrateur approuve un événement pour la publication.
     */
    public static function publicationApproved(Event $event): void
    {
        $organizerIds = self::organizerIdsForEvent($event);
        if ($organizerIds !== []) {
            self::send(
                $organizerIds,
                'organizer_publication_approved',
                'Publication approuvée',
                sprintf('« %s » est maintenant publié en ligne.', self::eventTitle($event)),
                ['event_id' => self::modelId($event), 'link' => '/events/'.self::modelId($event)],
            );
        }

        // Déclencher la diffusion générale et la notification au client.
        self::eventPublished($event);
    }

    /**
     * Diffuse que l'événement est maintenant en ligne à tous les participants et au client original.
     */
    public static function eventPublished(Event $event): void
    {
        // La diffusion aux participants peut toucher des dizaines de milliers de comptes ; elle sort donc du cycle HTTP.
        FanOutPublishedEventNotifications::dispatch(
            self::modelId($event),
            'Nouvel événement',
            sprintf('« %s » est disponible à l’inscription.', self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'link' => '/events/'.self::modelId($event)],
        );

        // Notifier le client qui a initialement demandé l'événement.
        $client = self::clientUserForEvent($event);
        if ($client) {
            self::send(
                self::modelId($client),
                'client_event_published',
                'Événement publié',
                sprintf('Votre événement « %s » est maintenant en ligne.', self::eventTitle($event)),
                ['event_id' => self::modelId($event), 'link' => '/client/stats'],
            );
        }
    }

    /**
     * Notifie les administrateurs et les organisateurs lorsqu'un participant s'inscrit à un événement.
     */
    public static function participantRegistered(Registration $registration): void
    {
        $registration->loadMissing(['event', 'user']);
        $event = $registration->event;
        // Notifier uniquement pour les événements en ligne afin d'éviter le bruit pendant les phases de configuration ou de brouillon.
        if (! $event || $event->status !== 'published') {
            return;
        }

        $participant = $registration->relationLoaded('user') ? $registration->getRelation('user') : null;
        $name = $participant instanceof User ? self::stringValue($participant->getAttribute('name')) : 'Un participant';

        self::send(
            self::adminIds(),
            'admin_participant_registered',
            'Nouvelle inscription',
            sprintf('%s s’est inscrit à « %s ».', $name, self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'registration_id' => self::modelId($registration), 'link' => '/admin/registrations'],
        );

        self::send(
            self::organizerIdsForEvent($event),
            'organizer_participant_registered',
            'Nouvelle inscription',
            sprintf('%s s’est inscrit à « %s ».', $name, self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'link' => '/organizer/registrations'],
        );
    }

    /**
     * Notifie les administrateurs et les organisateurs lorsqu'un participant effectue le paiement de son inscription.
     */
    public static function participantPaid(Registration $registration): void
    {
        $registration->loadMissing(['event', 'user']);
        $event = $registration->event;
        if (! $event || $event->status !== 'published') {
            return;
        }

        $participant = $registration->relationLoaded('user') ? $registration->getRelation('user') : null;
        $name = $participant instanceof User ? self::stringValue($participant->getAttribute('name')) : 'Un participant';

        self::send(
            self::adminIds(),
            'admin_participant_paid',
            'Paiement reçu',
            sprintf('%s a payé son billet pour « %s ».', $name, self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'registration_id' => self::modelId($registration), 'link' => '/admin/registrations'],
        );

        self::send(
            self::organizerIdsForEvent($event),
            'organizer_participant_paid',
            'Billet payé',
            sprintf('%s a payé pour « %s ».', $name, self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'link' => '/organizer/registrations'],
        );
    }

    /**
     * Notifie les administrateurs et les organisateurs lorsqu'un participant soumet un avis pour un événement.
     * L'avis nécessite généralement une modération avant d'être visible publiquement.
     */
    public static function feedbackSubmitted(Feedback $feedback): void
    {
        $feedback->loadMissing(['event', 'user']);
        $event = $feedback->event;
        if (! $event || $event->status !== 'published') {
            return;
        }

        $feedbackAuthor = $feedback->relationLoaded('user') ? $feedback->getRelation('user') : null;
        $author = $feedbackAuthor instanceof User ? self::stringValue($feedbackAuthor->getAttribute('name')) : 'Un participant';

        self::send(
            self::adminIds(),
            'admin_feedback_received',
            'Nouvel avis',
            sprintf('%s a laissé un avis sur « %s » (en attente de validation).', $author, self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'feedback_id' => self::modelId($feedback), 'link' => '/events/'.self::modelId($event)],
        );

        self::send(
            self::organizerIdsForEvent($event),
            'organizer_feedback_received',
            'Nouvel avis',
            sprintf('%s a laissé un avis sur « %s ».', $author, self::eventTitle($event)),
            ['event_id' => self::modelId($event), 'link' => '/organizer/events/'.self::modelId($event)],
        );
    }

    /**
     * Notifie l'auteur et le client de l'événement lorsqu'un avis est approuvé et publié.
     */
    public static function feedbackApproved(Feedback $feedback): void
    {
        $feedback->loadMissing(['event', 'user']);
        $event = $feedback->event;

        // Notifier l'auteur que son avis est maintenant en ligne.
        if ($feedback->user_id) {
            self::send(
                self::stringValue($feedback->user_id),
                'participant_feedback_approved',
                'Avis publié',
                sprintf('Votre avis sur « %s » a été publié.', $event instanceof Event ? self::eventTitle($event) : 'l’événement'),
                ['event_id' => $event instanceof Event ? self::modelId($event) : null, 'link' => $event instanceof Event ? '/events/'.self::modelId($event) : '/my-registrations'],
            );
        }

        // Notifier le client du nouvel avis public sur son événement.
        if ($event && $event->status === 'published') {
            $client = self::clientUserForEvent($event);
            // Ne pas notifier le client s'il est celui qui a écrit l'avis (peu probable mais possible).
            if ($client && $client->id !== $feedback->user_id) {
                $feedbackAuthor = $feedback->relationLoaded('user') ? $feedback->getRelation('user') : null;
                $author = $feedbackAuthor instanceof User ? self::stringValue($feedbackAuthor->getAttribute('name')) : 'Un participant';
                self::send(
                    self::modelId($client),
                    'client_feedback_on_event',
                    'Nouveau commentaire',
                    sprintf('%s a publié un avis sur « %s ».', $author, self::eventTitle($event)),
                    ['event_id' => self::modelId($event), 'link' => '/client/stats'],
                );
            }
        }
    }

    private static function eventTitle(Event $event): string
    {
        return self::stringValue($event->getAttribute('title'));
    }

    private static function modelId(Model $model): string
    {
        $id = $model->getKey();

        return is_int($id) || is_string($id) ? (string) $id : '';
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

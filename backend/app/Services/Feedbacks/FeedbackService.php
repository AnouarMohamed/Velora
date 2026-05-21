<?php

namespace App\Services\Feedbacks;

use App\Exceptions\FeedbackException;
use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Feedback;
use App\Models\Registration;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service gérant le cycle de vie et la visibilité des commentaires d'événements (Feedbacks).
 *
 * Ce service gère la soumission des avis par les participants, la modération par les administrateurs,
 * et les règles de visibilité complexes qui déterminent qui peut voir quel commentaire en fonction de son rôle
 * et de sa relation avec l'événement.
 *
 * Les commentaires ont une visibilité plus stricte que les événements : les administrateurs peuvent modérer les
 * commentaires en attente, mais les utilisateurs réguliers ne voient que les commentaires approuvés une fois
 * que l'événement lui-même leur est visible.
 */
class FeedbackService
{
    /**
     * Liste les commentaires pour un événement spécifique en fonction du rôle du spectateur.
     *
     * @param  User  $viewer  L'utilisateur demandant la liste.
     * @param  Event  $event  L'événement pour lequel les avis sont demandés.
     * @return Collection<int, Feedback>
     *
     * @throws FeedbackException Si le spectateur n'est pas autorisé à voir les commentaires de cet événement.
     */
    public function listForEvent(User $viewer, Event $event): Collection
    {
        // Masquer d'abord tout l'événement si le spectateur ne doit pas savoir qu'il existe.
        $this->ensureEventIsVisibleTo($viewer, $event);

        // Appliquer ensuite les règles de rôle spécifiques aux commentaires pour l'événement visible.
        $this->ensureCanViewFeedbacks($viewer, $event);

        $query = Feedback::query()
            ->where('event_id', $event->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc');

        // Les commentaires en attente sont des données de modération ; les consommateurs non-administrateurs
        // ne reçoivent que les commentaires publics (approuvés).
        if (! $viewer->isAdmin()) {
            $query->where('status', Feedback::STATUS_APPROVED);
        }

        return $query->get();
    }

    /**
     * Soumet ou met à jour le commentaire d'un participant pour un événement.
     *
     * @param  array{rating: int, comment?: string|null}  $data
     *
     * @throws FeedbackException Si l'utilisateur n'est pas un participant, si l'événement n'est pas en ligne, ou s'il n'a pas payé.
     */
    public function submit(User $participant, Event $event, array $data): Feedback
    {
        if ($participant->getAttribute('role') !== User::ROLE_PARTICIPANT) {
            throw new FeedbackException('Cette action n\'est pas autorisée.', 403);
        }

        if ($event->getAttribute('status') !== Event::STATUS_PUBLISHED) {
            throw new FeedbackException('Événement non disponible.');
        }

        // Une inscription payée est la preuve que le participant est autorisé à donner son avis sur cet événement.
        if (! $this->participantHasPaidRegistration($participant, $event)) {
            throw new FeedbackException('Inscription payante requise pour laisser un avis.', 403);
        }

        // Les participants peuvent réviser leur avis, mais chaque révision retourne en modération.
        $feedback = Feedback::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $participant->id,
            ],
            [
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'status' => Feedback::STATUS_PENDING,
            ],
        );

        $feedback->load('user:id,name', 'event');

        // Le personnel doit examiner les premières soumissions ainsi que les révisions ultérieures avant publication.
        NotificationService::feedbackSubmitted($feedback);

        return $feedback;
    }

    /**
     * Approuve un commentaire, le rendant visible au public.
     *
     * @throws FeedbackException Si le réviseur n'est pas un administrateur.
     */
    public function approve(User $reviewer, Feedback $feedback): FeedbackApprovalResult
    {
        if (! $reviewer->isAdmin()) {
            throw new FeedbackException('Cette action n\'est pas autorisée.', 403);
        }

        if ($feedback->getAttribute('status') === Feedback::STATUS_APPROVED) {
            return new FeedbackApprovalResult(
                $feedback->load('user:id,name'),
                'Cet avis est déjà publié.',
            );
        }

        $feedback->update(['status' => Feedback::STATUS_APPROVED]);
        $feedback->load('user:id,name', 'event');

        // L'approbation modifie la visibilité publique, l'auteur et les parties prenantes de l'événement sont donc notifiés.
        NotificationService::feedbackApproved($feedback);

        return new FeedbackApprovalResult($feedback, 'Avis publié.');
    }

    /**
     * Supprime un commentaire (Administrateur uniquement).
     */
    public function delete(User $reviewer, Feedback $feedback): void
    {
        if (! $reviewer->isAdmin()) {
            throw new FeedbackException('Cette action n\'est pas autorisée.', 403);
        }

        $feedback->delete();
    }

    /**
     * S'assure que l'événement est globalement visible (soit publié, soit géré par le spectateur).
     */
    private function ensureEventIsVisibleTo(User $viewer, Event $event): void
    {
        if ($event->getAttribute('status') === Event::STATUS_PUBLISHED || $this->canManageEvent($viewer, $event)) {
            return;
        }

        throw new FeedbackException('Non trouvé.', 404);
    }

    /**
     * Applique un contrôle d'accès granulaire pour la visualisation des commentaires.
     *
     * Règles :
     * - Administrateurs : Peuvent tout voir.
     * - Participants : Peuvent voir les commentaires approuvés pour les événements publiés.
     * - Clients : Peuvent voir les commentaires approuvés pour leurs propres événements.
     * - Organisateurs : Peuvent voir les commentaires approuvés pour les événements qu'ils gèrent.
     */
    private function ensureCanViewFeedbacks(User $viewer, Event $event): void
    {
        // Charger ces relations une seule fois permet de garder les vérifications ci-dessous explicites
        // et d'éviter les branches de chargement différé (lazy-loading) cachées.
        $event->loadMissing(['creator:id,role', 'eventRequest']);

        if ($viewer->isAdmin()) {
            return;
        }

        if (
            $viewer->getAttribute('role') === User::ROLE_PARTICIPANT
            && $event->getAttribute('status') === Event::STATUS_PUBLISHED
        ) {
            return;
        }

        if ($viewer->getAttribute('role') === User::ROLE_CLIENT && $this->clientOwnsEvent($viewer, $event)) {
            return;
        }

        $creator = $event->getRelation('creator');

        if (
            $viewer->getAttribute('role') === User::ROLE_ORGANIZER
            && $creator instanceof User
            && $creator->getAttribute('role') === User::ROLE_ORGANIZER
        ) {
            if ($event->isOrganizer($viewer)) {
                return;
            }
        }

        throw new FeedbackException('Cette action n\'est pas autorisée.', 403);
    }

    /**
     * Vérifie si un utilisateur a des droits de gestion sur un événement.
     */
    private function canManageEvent(User $viewer, Event $event): bool
    {
        if ($viewer->isAdmin()) {
            return true;
        }

        return $event->isOrganizer($viewer);
    }

    /**
     * Vérifie si un utilisateur est le client qui a initialement demandé l'événement.
     */
    private function clientOwnsEvent(User $viewer, Event $event): bool
    {
        // La propriété du client est dérivée de la demande d'événement originale, et non de l'affectation de l'organisateur.
        $event->loadMissing('eventRequest');
        $eventRequest = $event->getRelation('eventRequest');

        return $eventRequest instanceof EventRequest
            && strcasecmp($this->stringValue($eventRequest->getAttribute('contact_email')), $this->stringValue($viewer->getAttribute('email'))) === 0;
    }

    /**
     * Vérifie si un participant a un paiement complété pour l'événement.
     */
    private function participantHasPaidRegistration(User $participant, Event $event): bool
    {
        return Registration::where('event_id', $event->id)
            ->where('user_id', $participant->id)
            ->where('payment_status', 'paid')
            ->exists();
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

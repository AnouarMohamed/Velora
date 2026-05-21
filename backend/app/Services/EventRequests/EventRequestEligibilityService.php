<?php

namespace App\Services\EventRequests;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\User;

/**
 * Service responsable de déterminer si un utilisateur est éligible pour soumettre une nouvelle demande d'événement.
 *
 * Ce service applique la règle métier selon laquelle un client ne peut avoir qu'un seul processus "actif" lié à un événement
 * à la fois (soit une demande en attente, soit un événement en cours).
 */
class EventRequestEligibilityService
{
    /**
     * Vérifie si un utilisateur est empêché de soumettre une nouvelle demande d'événement et en retourne la raison.
     *
     * @param  User  $client  L'utilisateur (client) dont on veut vérifier l'éligibilité.
     * @return string|null Retourne un identifiant de chaîne pour la raison si bloqué, ou null si éligible.
     */
    public function blockingReasonFor(User $client): ?string
    {
        return $this->blockingReasonForEmail($this->stringValue($client->getAttribute('email')));
    }

    /**
     * Vérifie l'éligibilité en fonction de l'e-mail de contact fourni.
     *
     * Règles d'éligibilité :
     * 1. S'il existe une demande PENDING avec cet e-mail, l'utilisateur est bloqué ('pending').
     * 2. S'il existe des demandes APPROVED, tous les événements associés doivent être PUBLISHED et FINISHED.
     *    - Si une demande approuvée existe mais qu'aucun événement ne lui est encore lié, elle est considérée comme un processus actif ('active_event').
     *    - Si un événement existe mais n'est pas publié ou pas terminé, il est considéré comme actif ('active_event').
     *
     * @param  string  $email  L'e-mail de contact à vérifier.
     * @return string|null Retourne 'pending', 'active_event' ou null si éligible.
     */
    public function blockingReasonForEmail(string $email): ?string
    {
        // Règle 1 : Empêcher plusieurs demandes en attente de la part du même utilisateur/e-mail.
        if (EventRequest::query()
            ->where('contact_email', $email)
            ->where('status', EventRequest::STATUS_PENDING)
            ->exists()
        ) {
            return 'pending';
        }

        // Règle 2 : Vérifier les événements en cours liés aux demandes précédemment approuvées.
        $approvedRequestIds = EventRequest::query()
            ->where('contact_email', $email)
            ->where('status', EventRequest::STATUS_APPROVED)
            ->pluck('id')
            ->map(fn (mixed $id): string => $this->stringValue($id))
            ->values()
            ->all();

        // Si aucune demande n'a jamais été approuvée, ils sont éligibles.
        if ($approvedRequestIds === []) {
            return null;
        }

        // Récupérer tous les événements associés à ces demandes approuvées.
        $eventsByRequestId = Event::query()
            ->whereIn('event_request_id', $approvedRequestIds)
            ->get()
            ->keyBy(fn (Event $event): string => $this->stringValue($event->getAttribute('event_request_id')));

        foreach ($approvedRequestIds as $approvedRequestId) {
            $event = $eventsByRequestId->get((string) $approvedRequestId);

            // Cas limite : si une demande est approuvée mais que l'enregistrement de l'événement n'a pas encore été créé/lié,
            // nous le traitons comme un processus actif pour éviter les conditions de concurrence ou les demandes orphelines.
            if (! $event instanceof Event) {
                return 'active_event';
            }

            // Un événement est considéré comme "terminé" uniquement s'il est PUBLISHED (état archivé) et que sa date de fin est passée.
            // S'il est encore en DRAFT ou que la date de fin est dans le futur, il est "actif".
            if ($event->getAttribute('status') !== Event::STATUS_PUBLISHED || ! $event->isFinished()) {
                return 'active_event';
            }
        }

        // Si toutes les demandes approuvées précédentes ont abouti à des événements terminés, l'utilisateur est de nouveau éligible.
        return null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

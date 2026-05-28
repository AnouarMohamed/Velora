<?php

namespace App\Services;

use App\Exceptions\EventManagementException;
use App\Models\Event;
use App\Models\User;

/**
 * Service pour gérer le cycle de vie principal des événements.
 *
 * Ce service gère la création, les mises à jour, la gestion de la capacité, l'assignation des organisateurs
 * et le flux de publication (projet -> en attente -> publié).
 * Il applique des règles métier strictes concernant les rôles des utilisateurs et les transitions de statut des événements.
 *
 * Les contrôleurs appellent ce service au lieu de modifier directement les événements afin que chaque point d'entrée
 * utilise les mêmes règles pour les permissions des organisateurs, l'approbation de publication et la sécurité de la capacité.
 */
class EventManagementService
{
    /**
     * @param  EventImageStorage  $images  Service pour la gestion des téléchargements d'images d'événements.
     */
    public function __construct(private readonly EventImageStorage $images) {}

    /**
     * Crée manuellement un nouvel événement (généralement par un organisateur ou un administrateur).
     *
     * @param  User  $actor  L'utilisateur créant l'événement.
     * @param  array<string, mixed>  $data  Attributs de l'événement.
     */
    public function create(User $actor, array $data): Event
    {
        // Stocker l'image optionnelle avant de créer l'événement pour que le document de l'événement ne conserve qu'un chemin de stockage.
        $imagePath = $this->images->storeBase64(
            $this->nullableString($data['image_data'] ?? null),
            $this->nullableString($data['image_mime'] ?? null),
        );

        // Les administrateurs peuvent créer un événement publié ; les organisateurs sont contraints au flux de projet/révision.
        $status = $this->statusForCreate($actor, $this->stringValue($data['status'] ?? Event::STATUS_DRAFT));

        $event = Event::create([
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

        // Les événements créés manuellement par les organisateurs nécessitent une prise de conscience de l'admin avant de pouvoir devenir publics.
        NotificationService::organizerEventCreated($event, $actor);

        // Les événements publiés créés par l'administrateur ignorent le flux de révision, les notifications de publication se produisent donc ici.
        if ($status === Event::STATUS_PUBLISHED) {
            NotificationService::eventPublished($event);
        }

        return $event;
    }

    /**
     * Met à jour un événement existant.
     *
     * @param  User  $actor  L'utilisateur effectuant la mise à jour.
     * @param  Event  $event  L'événement à mettre à jour.
     * @param  array<string, mixed>  $data  Attributs mis à jour.
     *
     * @throws EventManagementException Si l'autorisation ou une règle métier échoue.
     */
    public function update(User $actor, Event $event, array $data): Event
    {
        // Chaque mutation d'événement commence par une validation de propriété/admin.
        $this->ensureCanManage($actor, $event);

        // Les organisateurs peuvent modifier les détails de l'événement, mais les transitions de statut de publication restent contrôlées.
        $data = $this->dataAllowedForActor($actor, $data);

        // Les inscriptions existantes doivent rester valides après une modification de la capacité.
        $this->ensureCapacityCanHoldRegistrations($event, $data['capacity'] ?? null);

        $wasPublished = $event->status === Event::STATUS_PUBLISHED;
        $previousStatus = $event->status;

        $event->update($data);
        $event->refresh();

        // Les modifications de l'administrateur peuvent affecter la planification opérationnelle, les organisateurs assignés sont donc notifiés.
        if ($actor->isAdmin() && NotificationService::organizerIdsForEvent($event) !== []) {
            NotificationService::eventUpdatedByAdmin($event);
        }

        // La publication est la transition de statut qui modifie la visibilité des participants et l'accès à l'inscription.
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
     * Met à jour spécifiquement la capacité de l'événement.
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
     * Assigne un utilisateur spécifique comme organisateur principal d'un événement.
     */
    public function assignOrganizer(Event $event, string $organizerId): Event
    {
        // Les administrateurs sont acceptés ici car ils peuvent gérer le travail de niveau organisateur dans cette application.
        $organizer = User::query()
            ->whereKey($organizerId)
            ->whereIn('role', [User::ROLE_ORGANIZER, User::ROLE_ADMIN])
            ->firstOrFail();

        $event->update(['organizer_id' => $organizer->id]);
        $event->refresh();

        // Seuls les vrais organisateurs ont besoin d'une notification d'assignation ; les administrateurs voient déjà les tableaux de bord globaux.
        if ($organizer->role === User::ROLE_ORGANIZER) {
            NotificationService::eventAssigned($event, $organizer);
        }

        return $event->load('organizer');
    }

    /**
     * Fait passer un événement de "projet" (draft) à "en attente de publication" (pending_publication).
     *
     * @throws EventManagementException
     */
    public function requestPublication(User $actor, Event $event): Event
    {
        $this->ensureCanManage($actor, $event);

        if ($actor->isAdmin()) {
            throw new EventManagementException('Publiez directement depuis l’espace administrateur.');
        }

        // Les événements au statut projet et en attente peuvent être soumis en toute sécurité ; les événements publiés/annulés/terminés ne le peuvent pas.
        if (! in_array($event->status, [Event::STATUS_DRAFT, Event::STATUS_PENDING_PUBLICATION], true)) {
            throw new EventManagementException('Cet événement ne peut pas être soumis à publication.');
        }

        $event->update(['status' => Event::STATUS_PENDING_PUBLICATION]);
        $event->refresh();

        // Les administrateurs sont les gardiens de la publication pour les événements créés par les organisateurs.
        NotificationService::publicationRequested($event, $actor);

        return $event;
    }

    /**
     * Approuve une demande de publication, rendant l'événement opérationnel.
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

        // Une fois publié, l'événement entre dans les flux de navigation et d'inscription.
        NotificationService::publicationApproved($event);

        return $event;
    }

    /**
     * S'assure que seuls les organisateurs assignés ou les administrateurs peuvent modifier un événement.
     */
    private function ensureCanManage(User $actor, Event $event): void
    {
        if (! $event->isOrganizer($actor)) {
            throw new EventManagementException('Accès refusé pour ce rôle.', 403);
        }
    }

    /**
     * Valide que la nouvelle capacité est suffisante pour les inscriptions actuelles.
     */
    private function ensureCapacityCanHoldRegistrations(Event $event, mixed $capacity): void
    {
        if ($capacity !== null && $this->intValue($capacity) < $this->intValue($event->registered_count)) {
            throw new EventManagementException('La capacité ne peut pas être inférieure au nombre d’inscrits.');
        }
    }

    /**
     * Détermine le statut initial en fonction du rôle. Les administrateurs peuvent contourner les flux de travail.
     */
    private function statusForCreate(User $actor, string $requestedStatus): string
    {
        if ($actor->isAdmin()) {
            return $requestedStatus;
        }

        // Les organisateurs peuvent demander une révision, mais ils ne peuvent pas rendre l'événement public de leur propre chef.
        return $requestedStatus === Event::STATUS_PENDING_PUBLICATION
            ? Event::STATUS_PENDING_PUBLICATION
            : Event::STATUS_DRAFT;
    }

    /**
     * Nettoie et restreint les attributs en fonction du rôle de l'utilisateur.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function dataAllowedForActor(User $actor, array $data): array
    {
        $status = $data['status'] ?? null;
        if (! is_string($status) || $actor->isAdmin()) {
            return $data;
        }

        // Les organisateurs ne peuvent pas définir le statut à "publié" via une mise à jour standard.
        if ($status === Event::STATUS_PUBLISHED) {
            throw new EventManagementException('Seul un administrateur peut publier l’événement. Envoyez une demande de publication.');
        }

        // Les statuts inconnus ou réservés aux administrateurs sont ignorés au lieu d'être acceptés à partir de la charge utile de la requête.
        if (! in_array($status, [
            Event::STATUS_DRAFT,
            Event::STATUS_PENDING_PUBLICATION,
            Event::STATUS_CANCELLED,
        ], true)) {
            unset($data['status']);
        }

        return $data;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) || is_float($value) || is_string($value) ? (int) $value : 0;
    }
}

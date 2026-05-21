<?php

namespace App\Services\EventRequests;

use App\Exceptions\EventRequestException;
use App\Models\EventRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\UploadedFile;

/**
 * Service orchestrant la soumission et la suppression des demandes d'événements par les clients.
 *
 * Il coordonne les vérifications d'éligibilité, le traitement des images, la persistance en base de données et les notifications.
 */
class EventRequestSubmissionService
{
    /**
     * @param  EventRequestEligibilityService  $eligibility  Service pour vérifier si l'utilisateur est autorisé à soumettre une demande.
     * @param  EventRequestImageStorage  $images  Service pour gérer la persistance des images.
     */
    public function __construct(
        private readonly EventRequestEligibilityService $eligibility,
        private readonly EventRequestImageStorage $images,
    ) {}

    /**
     * Soumet une nouvelle demande d'événement au nom d'un client.
     *
     * @param  User  $client  L'utilisateur soumettant la demande (doit avoir le rôle ROLE_CLIENT).
     * @param  array<string, mixed>  $data  Données de demande validées (détails de l'événement, données d'image, etc.).
     * @return EventRequest L'instance de la demande d'événement nouvellement créée.
     *
     * @throws EventRequestException Si l'utilisateur n'est pas un client ou est inéligible en raison de demandes/événements actifs.
     */
    public function submit(User $client, array $data): EventRequest
    {
        // Vérification de sécurité : seuls les clients peuvent demander des services d'organisation d'événements.
        if ($client->getAttribute('role') !== User::ROLE_CLIENT) {
            throw new EventRequestException('Cette action n\'est pas autorisée.', 403);
        }

        // Règle métier : un client ne peut avoir qu'une seule demande ou un seul événement actif à la fois.
        $blockReason = $this->eligibility->blockingReasonFor($client);
        if ($blockReason !== null) {
            throw new EventRequestException(
                $this->blockingMessage($blockReason),
                422,
                ['block_reason' => $blockReason],
            );
        }

        // Gérer la pièce jointe de l'image (peut être un UploadedFile provenant de form-data ou du base64 provenant du JSON).
        $image = $data['image'] ?? null;
        $imagePath = $this->images->store(
            $image instanceof UploadedFile ? $image : null,
            $this->nullableString($data['image_data'] ?? null),
            $this->nullableString($data['image_mime'] ?? null),
        );

        // Supprimer les données d'image éphémères avant de sauvegarder en base de données.
        unset($data['image'], $data['image_data'], $data['image_mime']);

        // Persister la demande dans l'état PENDING.
        $eventRequest = EventRequest::create([
            ...$data,
            'user_id' => $client->getKey(),
            'image_path' => $imagePath,
            'status' => EventRequest::STATUS_PENDING,
        ]);

        // Notifier les administrateurs de la nouvelle soumission.
        NotificationService::eventRequestSubmitted($eventRequest);

        return $eventRequest->fresh() ?? $eventRequest;
    }

    /**
     * Supprime une demande d'événement existante.
     *
     * Seules les demandes en attente peuvent être supprimées par leur propriétaire.
     *
     * @param  User  $client  L'utilisateur tentant de supprimer la demande.
     * @param  EventRequest  $eventRequest  La demande à supprimer.
     *
     * @throws EventRequestException Si la demande n'appartient pas au client ou n'est pas dans l'état PENDING.
     */
    public function delete(User $client, EventRequest $eventRequest): void
    {
        // Vérification de la propriété basée sur l'e-mail de contact (les clients peuvent ne pas toujours être connectés lors de certains flux).
        if (strcasecmp($this->stringValue($eventRequest->getAttribute('contact_email')), $this->stringValue($client->getAttribute('email'))) !== 0) {
            throw new EventRequestException('Demande introuvable.', 404);
        }

        // Règle métier : une fois qu'une demande est approuvée ou rejetée, elle ne peut plus être supprimée par le client.
        if ($eventRequest->getAttribute('status') !== EventRequest::STATUS_PENDING) {
            throw new EventRequestException('Seules les demandes en attente peuvent être supprimées.');
        }

        // Nettoyage : supprimer l'image associée du stockage.
        $imagePath = $eventRequest->getAttribute('image_path');
        $this->images->delete(is_string($imagePath) ? $imagePath : null);

        $eventRequest->delete();
    }

    /**
     * Traduit les raisons de blocage internes en messages d'erreur conviviaux pour l'utilisateur.
     *
     * @param  string  $blockReason  L'identifiant de la raison provenant d'EligibilityService.
     * @return string Message compréhensible par l'homme en français.
     */
    private function blockingMessage(string $blockReason): string
    {
        return $blockReason === 'pending'
            ? 'Vous avez déjà une demande en attente. Supprimez-la pour en envoyer une nouvelle.'
            : 'Votre événement est encore en cours. Attendez sa fin pour envoyer une nouvelle demande.';
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

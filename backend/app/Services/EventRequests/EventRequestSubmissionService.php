<?php

namespace App\Services\EventRequests;

use App\Exceptions\EventRequestException;
use App\Models\EventRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\UploadedFile;

class EventRequestSubmissionService
{
    public function __construct(
        private readonly EventRequestEligibilityService $eligibility,
        private readonly EventRequestImageStorage $images,
    ) {}

    /** @param array<string, mixed> $data */
    public function submit(User $client, array $data): EventRequest
    {
        if ($client->getAttribute('role') !== User::ROLE_CLIENT) {
            throw new EventRequestException('This action is unauthorized.', 403);
        }

        $blockReason = $this->eligibility->blockingReasonFor($client);
        if ($blockReason !== null) {
            throw new EventRequestException(
                $this->blockingMessage($blockReason),
                422,
                ['block_reason' => $blockReason],
            );
        }

        $image = $data['image'] ?? null;
        $imagePath = $this->images->store(
            $image instanceof UploadedFile ? $image : null,
            isset($data['image_data']) ? (string) $data['image_data'] : null,
            isset($data['image_mime']) ? (string) $data['image_mime'] : null,
        );

        unset($data['image'], $data['image_data'], $data['image_mime']);

        $eventRequest = EventRequest::create([
            ...$data,
            'user_id' => $client->getKey(),
            'image_path' => $imagePath,
            'status' => EventRequest::STATUS_PENDING,
        ]);

        NotificationService::eventRequestSubmitted($eventRequest);

        return $eventRequest->fresh() ?? $eventRequest;
    }

    public function delete(User $client, EventRequest $eventRequest): void
    {
        if (strcasecmp((string) $eventRequest->getAttribute('contact_email'), (string) $client->getAttribute('email')) !== 0) {
            throw new EventRequestException('Demande introuvable.', 404);
        }

        if ($eventRequest->getAttribute('status') !== EventRequest::STATUS_PENDING) {
            throw new EventRequestException('Seules les demandes en attente peuvent être supprimées.');
        }

        $imagePath = $eventRequest->getAttribute('image_path');
        $this->images->delete(is_string($imagePath) ? $imagePath : null);
        $eventRequest->delete();
    }

    private function blockingMessage(string $blockReason): string
    {
        return $blockReason === 'pending'
            ? 'Vous avez déjà une demande en attente. Supprimez-la pour en envoyer une nouvelle.'
            : 'Votre événement est encore en cours. Attendez sa fin pour envoyer une nouvelle demande.';
    }
}

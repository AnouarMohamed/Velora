<?php

namespace App\Services\EventRequests;

use App\Exceptions\EventRequestException;
use App\Models\EventRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\UploadedFile;

/**
 * Service orchestrating the submission and deletion of event requests by clients.
 *
 * It coordinates eligibility checks, image processing, database persistence, and notifications.
 */
class EventRequestSubmissionService
{
    /**
     * @param  EventRequestEligibilityService  $eligibility  Service to check if the user is allowed to submit.
     * @param  EventRequestImageStorage  $images  Service to handle image persistence.
     */
    public function __construct(
        private readonly EventRequestEligibilityService $eligibility,
        private readonly EventRequestImageStorage $images,
    ) {}

    /**
     * Submits a new event request on behalf of a client.
     *
     * @param  User  $client  The user submitting the request (must have ROLE_CLIENT).
     * @param  array<string, mixed>  $data  Validated request data (event details, image data, etc.).
     * @return EventRequest The newly created event request instance.
     *
     * @throws EventRequestException If the user is not a client or is ineligible due to active requests/events.
     */
    public function submit(User $client, array $data): EventRequest
    {
        // Security check: Only clients can request event organization services.
        if ($client->getAttribute('role') !== User::ROLE_CLIENT) {
            throw new EventRequestException('This action is unauthorized.', 403);
        }

        // Business rule: A client can only have one active request or event at a time.
        $blockReason = $this->eligibility->blockingReasonFor($client);
        if ($blockReason !== null) {
            throw new EventRequestException(
                $this->blockingMessage($blockReason),
                422,
                ['block_reason' => $blockReason],
            );
        }

        // Handle image attachment (could be UploadedFile from form-data or base64 from JSON).
        $image = $data['image'] ?? null;
        $imagePath = $this->images->store(
            $image instanceof UploadedFile ? $image : null,
            isset($data['image_data']) ? (string) $data['image_data'] : null,
            isset($data['image_mime']) ? (string) $data['image_mime'] : null,
        );

        // Remove ephemeral image data before saving to DB.
        unset($data['image'], $data['image_data'], $data['image_mime']);

        // Persist the request in PENDING state.
        $eventRequest = EventRequest::create([
            ...$data,
            'user_id' => $client->getKey(),
            'image_path' => $imagePath,
            'status' => EventRequest::STATUS_PENDING,
        ]);

        // Notify admins about the new submission.
        NotificationService::eventRequestSubmitted($eventRequest);

        return $eventRequest->fresh() ?? $eventRequest;
    }

    /**
     * Deletes an existing event request.
     *
     * Only pending requests can be deleted by their owner.
     *
     * @param  User  $client  The user attempting to delete the request.
     * @param  EventRequest  $eventRequest  The request to be deleted.
     *
     * @throws EventRequestException If the request is not owned by the client or is not in PENDING state.
     */
    public function delete(User $client, EventRequest $eventRequest): void
    {
        // Ownership check based on contact email (clients might not always be logged in during some flows).
        if (strcasecmp((string) $eventRequest->getAttribute('contact_email'), (string) $client->getAttribute('email')) !== 0) {
            throw new EventRequestException('Demande introuvable.', 404);
        }

        // Business rule: Once a request is approved or rejected, it cannot be deleted by the client.
        if ($eventRequest->getAttribute('status') !== EventRequest::STATUS_PENDING) {
            throw new EventRequestException('Seules les demandes en attente peuvent être supprimées.');
        }

        // Cleanup: Delete the associated image from storage.
        $imagePath = $eventRequest->getAttribute('image_path');
        $this->images->delete(is_string($imagePath) ? $imagePath : null);

        $eventRequest->delete();
    }

    /**
     * Translates internal blocking reasons into user-friendly error messages.
     *
     * @param  string  $blockReason  The reason identifier from EligibilityService.
     * @return string Human-readable message in French.
     */
    private function blockingMessage(string $blockReason): string
    {
        return $blockReason === 'pending'
            ? 'Vous avez déjà une demande en attente. Supprimez-la pour en envoyer une nouvelle.'
            : 'Votre événement est encore en cours. Attendez sa fin pour envoyer une nouvelle demande.';
    }
}

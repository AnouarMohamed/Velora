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
 * Service managing the lifecycle and visibility of Event Feedbacks.
 *
 * This service handles the submission of reviews by participants, moderation by admins,
 * and complex visibility rules that determine who can see which feedback based on their role
 * and relationship to the event.
 *
 * Feedback has stricter visibility than events: admins can moderate pending feedback, but
 * regular users only see approved feedback after the event itself is visible to them.
 */
class FeedbackService
{
    /**
     * Lists feedbacks for a specific event based on the viewer's role.
     *
     * @param  User  $viewer  The user requesting the list.
     * @param  Event  $event  The event reviews are for.
     * @return Collection<int, Feedback>
     *
     * @throws FeedbackException If the viewer is not authorized to see feedbacks for this event.
     */
    public function listForEvent(User $viewer, Event $event): Collection
    {
        // First hide the whole event when the viewer should not know it exists.
        $this->ensureEventIsVisibleTo($viewer, $event);

        // Then apply feedback-specific role rules for the visible event.
        $this->ensureCanViewFeedbacks($viewer, $event);

        $query = Feedback::query()
            ->where('event_id', $event->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc');

        // Pending feedback is moderation data; non-admin consumers receive public feedback only.
        if (! $viewer->isAdmin()) {
            $query->where('status', Feedback::STATUS_APPROVED);
        }

        return $query->get();
    }

    /**
     * Submits or updates a participant's feedback for an event.
     *
     * @param  array{rating: int, comment?: string|null}  $data
     *
     * @throws FeedbackException If the user is not a participant, event is not live, or they haven't paid.
     */
    public function submit(User $participant, Event $event, array $data): Feedback
    {
        if ($participant->getAttribute('role') !== User::ROLE_PARTICIPANT) {
            throw new FeedbackException('This action is unauthorized.', 403);
        }

        if ($event->getAttribute('status') !== Event::STATUS_PUBLISHED) {
            throw new FeedbackException('Événement non disponible.');
        }

        // A paid registration is the proof that the participant is allowed to review this event.
        if (! $this->participantHasPaidRegistration($participant, $event)) {
            throw new FeedbackException('Inscription payante requise pour laisser un avis.', 403);
        }

        // Participants may revise their feedback, but every revision returns to moderation.
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

        // Staff must review both first submissions and later revisions before publication.
        NotificationService::feedbackSubmitted($feedback);

        return $feedback;
    }

    /**
     * Approves a feedback, making it visible to the public.
     *
     * @throws FeedbackException If the reviewer is not an admin.
     */
    public function approve(User $reviewer, Feedback $feedback): FeedbackApprovalResult
    {
        if (! $reviewer->isAdmin()) {
            throw new FeedbackException('This action is unauthorized.', 403);
        }

        if ($feedback->getAttribute('status') === Feedback::STATUS_APPROVED) {
            return new FeedbackApprovalResult(
                $feedback->load('user:id,name'),
                'Cet avis est déjà publié.',
            );
        }

        $feedback->update(['status' => Feedback::STATUS_APPROVED]);
        $feedback->load('user:id,name', 'event');

        // Approval changes public visibility, so the author and event-side stakeholders are notified.
        NotificationService::feedbackApproved($feedback);

        return new FeedbackApprovalResult($feedback, 'Avis publié.');
    }

    /**
     * Deletes a feedback (Admin only).
     */
    public function delete(User $reviewer, Feedback $feedback): void
    {
        if (! $reviewer->isAdmin()) {
            throw new FeedbackException('This action is unauthorized.', 403);
        }

        $feedback->delete();
    }

    /**
     * Ensures the event is generally visible (either published or managed by the viewer).
     */
    private function ensureEventIsVisibleTo(User $viewer, Event $event): void
    {
        if ($event->getAttribute('status') === Event::STATUS_PUBLISHED || $this->canManageEvent($viewer, $event)) {
            return;
        }

        throw new FeedbackException('Not found.', 404);
    }

    /**
     * Enforces granular access control for viewing feedbacks.
     *
     * Rules:
     * - Admins: Can see everything.
     * - Participants: Can see approved feedbacks for published events.
     * - Clients: Can see approved feedbacks for their own events.
     * - Organizers: Can see approved feedbacks for events they manage.
     */
    private function ensureCanViewFeedbacks(User $viewer, Event $event): void
    {
        // Loading these relations once keeps the checks below explicit and avoids hidden lazy-loading branches.
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

        throw new FeedbackException('This action is unauthorized.', 403);
    }

    /**
     * Checks if a user has management rights over an event.
     */
    private function canManageEvent(User $viewer, Event $event): bool
    {
        if ($viewer->isAdmin()) {
            return true;
        }

        return $event->isOrganizer($viewer);
    }

    /**
     * Checks if a user is the client who originally requested the event.
     */
    private function clientOwnsEvent(User $viewer, Event $event): bool
    {
        // Client ownership is derived from the original event request, not from organizer assignment.
        $event->loadMissing('eventRequest');
        $eventRequest = $event->getRelation('eventRequest');

        return $eventRequest instanceof EventRequest
            && strcasecmp($eventRequest->getAttribute('contact_email'), $viewer->getAttribute('email')) === 0;
    }

    /**
     * Checks if a participant has a completed payment for the event.
     */
    private function participantHasPaidRegistration(User $participant, Event $event): bool
    {
        return Registration::where('event_id', $event->id)
            ->where('user_id', $participant->id)
            ->where('payment_status', 'paid')
            ->exists();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Feedback;
use App\Models\Registration;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request, Event $event)
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($event->status !== 'published' && ! $this->canManageEvent($user, $event)) {
            abort(404);
        }

        $query = Feedback::query()
            ->where('event_id', $event->id)
            ->with('user:id,name')
            ->latest();

        abort_unless($this->canViewFeedbacks($user, $event), 403);

        if (! $user->isAdmin()) {
            $query->where('status', Feedback::STATUS_APPROVED);
        }

        $items = $query->get()->map(fn (Feedback $f) => $this->formatFeedback($f));

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, Event $event)
    {
        abort_unless($request->user()->role === User::ROLE_PARTICIPANT, 403);
        abort_unless($event->status === 'published', 422, 'Événement non disponible.');

        $hasPaid = Registration::where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->where('payment_status', 'paid')
            ->exists();

        abort_unless($hasPaid, 403, 'Inscription payante requise pour laisser un avis.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $feedback = Feedback::updateOrCreate(
            [
                'event_id' => $event->id,
                'user_id' => $request->user()->id,
            ],
            [
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
                'status' => 'pending',
            ]
        );

        $feedback->load('user:id,name', 'event');

        NotificationService::feedbackSubmitted($feedback);

        return response()->json([
            'data' => $this->formatFeedback($feedback),
            'message' => 'Votre avis a bien été envoyé. Il sera visible après validation par notre équipe.',
        ], 201);
    }

    public function approve(Request $request, Feedback $feedback)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($feedback->status === 'approved') {
            return response()->json([
                'data' => $this->formatFeedback($feedback->load('user:id,name')),
                'message' => 'Cet avis est déjà publié.',
            ]);
        }

        $feedback->update(['status' => 'approved']);
        $feedback->load('user:id,name', 'event');

        NotificationService::feedbackApproved($feedback);

        return response()->json([
            'data' => $this->formatFeedback($feedback),
            'message' => 'Avis publié.',
        ]);
    }

    public function destroy(Request $request, Feedback $feedback)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $feedback->delete();

        return response()->json(null, 204);
    }

    private function formatFeedback(Feedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'event_id' => $feedback->event_id,
            'rating' => (int) $feedback->rating,
            'comment' => $feedback->comment,
            'status' => $feedback->status,
            'created_at' => $feedback->created_at,
            'user' => $feedback->user ? [
                'id' => $feedback->user->id,
                'name' => $feedback->user->name,
            ] : null,
        ];
    }

    private function canManageEvent(User $user, Event $event): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $event->isOrganizer($user);
    }

    private function canViewFeedbacks(User $user, Event $event): bool
    {
        $event->loadMissing(['creator:id,role', 'eventRequest']);

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->role === User::ROLE_PARTICIPANT && $event->status === 'published') {
            return true;
        }

        if ($user->role === User::ROLE_CLIENT && $this->clientOwnsEvent($user, $event)) {
            return true;
        }

        if ($user->role === User::ROLE_ORGANIZER && $event->creator?->role === User::ROLE_ORGANIZER) {
            return $event->isOrganizer($user);
        }

        return false;
    }

    private function clientOwnsEvent(User $user, Event $event): bool
    {
        $event->loadMissing('eventRequest');

        return $event->eventRequest
            && strcasecmp($event->eventRequest->contact_email, $user->email) === 0;
    }
}

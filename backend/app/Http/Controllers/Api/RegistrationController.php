<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registrations) {}

    /** Colonnes événement chargées avec les inscriptions participant. */
    private function registrationEventSelect(): array
    {
        return [
            'id',
            'event_request_id',
            'title',
            'description',
            'start_at',
            'end_at',
            'location',
            'room',
            'ticket_price_cents',
            'status',
            'image_path',
        ];
    }

    private function registrationEventWith(): array
    {
        return [
            'event' => fn ($q) => $q->select($this->registrationEventSelect()),
            'event.eventRequest' => fn ($q) => $q->select('id', 'image_path'),
        ];
    }

    public function store(Request $request, Event $event)
    {
        abort_unless($request->user()->role === User::ROLE_PARTICIPANT, 403);

        $registration = $this->registrations->register($request->user(), $event);

        return response()->json($registration, 201);
    }

    public function pay(Request $request, Registration $registration)
    {
        $user = $request->user();
        abort_unless($registration->user_id === $user->id, 403);
        abort_unless($user->role === User::ROLE_PARTICIPANT, 403);

        $registration = $this->registrations->pay($registration);

        return response()->json($registration);
    }

    public function destroy(Request $request, Registration $registration)
    {
        $user = $request->user();
        abort_unless($registration->user_id === $user->id, 403);
        abort_unless($user->role === User::ROLE_PARTICIPANT, 403);

        $this->registrations->cancel($registration);

        return response()->json(['message' => 'Inscription annulée.']);
    }

    public function myRegistrationForEvent(Request $request, Event $event)
    {
        abort_unless($request->user()->role === User::ROLE_PARTICIPANT, 403);

        $registration = Registration::query()
            ->where('user_id', $request->user()->id)
            ->where('event_id', $event->id)
            ->with($this->registrationEventWith())
            ->first();

        return response()->json(['registration' => $registration]);
    }

    public function myRegistrations(Request $request)
    {
        $query = Registration::query()
            ->where('user_id', $request->user()->id)
            ->with($this->registrationEventWith())
            ->orderBy('created_at', 'desc');

        if ($request->filled('payment_status')) {
            $status = $request->string('payment_status')->toString();
            if (in_array($status, ['paid', 'pending'], true)) {
                $query->where('payment_status', $status);
            }
        }

        return response()->json($query->paginate(20));
    }

    public function ticket(Request $request, Registration $registration): StreamedResponse
    {
        abort_unless($registration->user_id === $request->user()->id, 403);
        abort_unless($registration->payment_status === 'paid', 422, 'Paiement requis pour le billet.');

        $registration->load('event', 'user');
        $payload = [
            'ticket' => $registration->ticket_code,
            'event' => $registration->event->title,
            'participant' => $registration->user->name,
            'starts_at' => $registration->event->start_at->toIso8601String(),
            'location' => $registration->event->location,
        ];

        $filename = 'billet-'.$registration->id.'.json';

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}

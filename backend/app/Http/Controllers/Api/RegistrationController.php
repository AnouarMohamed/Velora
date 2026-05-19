<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\ParticipantRegistrationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationController extends Controller
{
    public function __construct(private readonly ParticipantRegistrationService $registrations) {}

    public function store(Request $request, Event $event)
    {
        $registration = $this->registrations->register($this->actor($request), $event);

        return response()->json($registration, 201);
    }

    public function pay(Request $request, Registration $registration)
    {
        $registration = $this->registrations->pay($this->actor($request), $registration);

        return response()->json($registration);
    }

    public function destroy(Request $request, Registration $registration)
    {
        $this->registrations->cancel($this->actor($request), $registration);

        return response()->json(['message' => 'Inscription annulée.']);
    }

    public function myRegistrationForEvent(Request $request, Event $event)
    {
        $registration = $this->registrations->registrationForEvent($this->actor($request), $event);

        return response()->json(['registration' => $registration]);
    }

    public function myRegistrations(Request $request)
    {
        $status = $request->filled('payment_status')
            ? $request->string('payment_status')->toString()
            : null;

        return response()->json($this->registrations->listForParticipant($this->actor($request), $status));
    }

    public function ticket(Request $request, Registration $registration): StreamedResponse
    {
        $ticket = $this->registrations->ticketFor($this->actor($request), $registration);

        return response()->streamDownload(function () use ($ticket): void {
            echo json_encode($ticket->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $ticket->filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}

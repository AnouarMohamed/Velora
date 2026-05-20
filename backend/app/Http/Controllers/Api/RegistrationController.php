<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\ParticipantRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for managing participant registrations to events.
 */
class RegistrationController extends Controller
{
    /**
     * @param  ParticipantRegistrationService  $registrations  Service for participant registration workflows.
     */
    public function __construct(private readonly ParticipantRegistrationService $registrations) {}

    /**
     * Register the authenticated user for an event.
     *
     * @return JsonResponse 201 Created with registration details.
     */
    public function store(Request $request, Event $event)
    {
        $registration = $this->registrations->register($this->actor($request), $event);

        return response()->json($registration, 201);
    }

    /**
     * Mark a registration as paid.
     *
     * In a real app, this would be triggered by a payment provider callback.
     *
     * @return JsonResponse Updated registration.
     */
    public function pay(Request $request, Registration $registration)
    {
        $registration = $this->registrations->pay($this->actor($request), $registration);

        return response()->json($registration);
    }

    /**
     * Cancel a registration.
     *
     * @return JsonResponse 200 OK message.
     */
    public function destroy(Request $request, Registration $registration)
    {
        $this->registrations->cancel($this->actor($request), $registration);

        return response()->json(['message' => 'Inscription annulée.']);
    }

    /**
     * Get the registration details for the current user for a specific event.
     *
     * Used by the UI to show "Already Registered" status or ticket download link.
     *
     * @return JsonResponse
     */
    public function myRegistrationForEvent(Request $request, Event $event)
    {
        $registration = $this->registrations->registrationForEvent($this->actor($request), $event);

        return response()->json(['registration' => $registration]);
    }

    /**
     * List all registrations for the authenticated user.
     *
     * @return JsonResponse List of registrations.
     */
    public function myRegistrations(Request $request)
    {
        // Optional filter by payment status (pending, paid)
        $status = $request->filled('payment_status')
            ? $request->string('payment_status')->toString()
            : null;

        return response()->json($this->registrations->listForParticipant($this->actor($request), $status));
    }

    /**
     * Download the ticket for a registration.
     *
     * Returns a JSON-formatted ticket file.
     */
    public function ticket(Request $request, Registration $registration): StreamedResponse
    {
        $ticket = $this->registrations->ticketFor($this->actor($request), $registration);

        return response()->streamDownload(function () use ($ticket): void {
            // Encode the ticket payload as JSON for the download
            echo json_encode($ticket->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $ticket->filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Retrieve and validate the authenticated user.
     */
    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}

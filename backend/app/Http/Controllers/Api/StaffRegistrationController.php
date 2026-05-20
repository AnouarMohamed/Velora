<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrations\StaffRegistrationIndexRequest;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\StaffRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing participant registrations from a staff (Organizer/Admin) perspective.
 *
 * This controller allows staff to view, search, and manage registrations for the events they manage.
 */
class StaffRegistrationController extends Controller
{
    /**
     * @param  StaffRegistrationService  $registrations  Service for staff-level registration management.
     */
    public function __construct(private readonly StaffRegistrationService $registrations) {}

    /**
     * List events managed by the current Organizer that have at least one registration.
     *
     * @return JsonResponse
     */
    public function eventsForOrganizer(Request $request)
    {
        return response()->json($this->registrations->eventsForOrganizer($this->actor($request)));
    }

    /**
     * List all events that have at least one registration (Admin view).
     *
     * @return JsonResponse
     */
    public function eventsForAdmin(Request $request)
    {
        return response()->json($this->registrations->eventsForAdmin($this->actor($request)));
    }

    /**
     * List registrations for an event managed by the Organizer.
     *
     * @param  StaffRegistrationIndexRequest  $request  Validated filters (event_id, status, search).
     * @return JsonResponse Paginated list of registrations.
     */
    public function indexForOrganizer(StaffRegistrationIndexRequest $request)
    {
        return response()->json($this->registrations->listForOrganizer(
            $this->actor($request),
            $request->validated(),
        ));
    }

    /**
     * List registrations for any event (Admin view).
     *
     * @param  StaffRegistrationIndexRequest  $request  Validated filters (event_id, status, search).
     * @return JsonResponse Paginated list of registrations.
     */
    public function indexForAdmin(StaffRegistrationIndexRequest $request)
    {
        return response()->json($this->registrations->listForAdmin(
            $this->actor($request),
            $request->validated(),
        ));
    }

    /**
     * Delete/Cancel a registration as an Organizer.
     *
     * @return JsonResponse 200 OK message.
     */
    public function destroyForOrganizer(Request $request, Registration $registration)
    {
        $this->registrations->deleteForOrganizer($this->actor($request), $registration);

        return response()->json(['message' => 'Inscription supprimée.']);
    }

    /**
     * Delete/Cancel a registration as an Admin.
     *
     * @return JsonResponse 200 OK message.
     */
    public function destroyForAdmin(Request $request, Registration $registration)
    {
        $this->registrations->deleteForAdmin($this->actor($request), $registration);

        return response()->json(['message' => 'Inscription supprimée.']);
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

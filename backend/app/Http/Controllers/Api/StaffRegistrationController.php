<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registrations\StaffRegistrationIndexRequest;
use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\StaffRegistrationService;
use Illuminate\Http\Request;

class StaffRegistrationController extends Controller
{
    public function __construct(private readonly StaffRegistrationService $registrations) {}

    public function eventsForOrganizer(Request $request)
    {
        return response()->json($this->registrations->eventsForOrganizer($this->actor($request)));
    }

    public function eventsForAdmin(Request $request)
    {
        return response()->json($this->registrations->eventsForAdmin($this->actor($request)));
    }

    public function indexForOrganizer(StaffRegistrationIndexRequest $request)
    {
        return response()->json($this->registrations->listForOrganizer(
            $this->actor($request),
            $request->validated(),
        ));
    }

    public function indexForAdmin(StaffRegistrationIndexRequest $request)
    {
        return response()->json($this->registrations->listForAdmin(
            $this->actor($request),
            $request->validated(),
        ));
    }

    public function destroyForOrganizer(Request $request, Registration $registration)
    {
        $this->registrations->deleteForOrganizer($this->actor($request), $registration);

        return response()->json(['message' => 'Inscription supprimée.']);
    }

    public function destroyForAdmin(Request $request, Registration $registration)
    {
        $this->registrations->deleteForAdmin($this->actor($request), $registration);

        return response()->json(['message' => 'Inscription supprimée.']);
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

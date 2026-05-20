<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\Users\UserWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing users from an Admin perspective.
 *
 * This controller allows Admins to list, create, update, and delete users.
 */
class UserAdminController extends Controller
{
    /**
     * @param  UserWriteService  $users  Service for user creation and updates.
     */
    public function __construct(private readonly UserWriteService $users) {}

    /**
     * List users (Admin view).
     *
     * @return JsonResponse Paginated list of users, optionally filtered by role.
     */
    public function index(Request $request)
    {
        $q = User::query()->orderBy('created_at', 'desc');

        // Optional role filter (admin, organizer, client)
        if ($role = $request->query('role')) {
            $q->where('role', $role);
        }

        return response()->json($q->paginate(30));
    }

    /**
     * Get a list of all Organizers.
     *
     * Used for populating dropdowns (e.g., when assigning an organizer to an event).
     *
     * @return JsonResponse List of organizers with basic fields.
     */
    public function organizers()
    {
        $users = User::query()
            ->where('role', User::ROLE_ORGANIZER)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json($users);
    }

    /**
     * Create a new user manually.
     *
     * @param  StoreUserRequest  $request  Validated user data.
     * @return JsonResponse 201 Created.
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->users->create($request->validated());

        return response()->json($user, 201);
    }

    /**
     * Update an existing user's profile.
     *
     * @param  UpdateUserRequest  $request  Validated user updates.
     * @param  User  $user  The user to update.
     * @return JsonResponse Updated user.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        return response()->json($this->users->update($user, $request->validated()));
    }

    /**
     * Delete a user account.
     *
     * @param  User  $user  The user to delete.
     * @return JsonResponse 204 No Content.
     */
    public function destroy(Request $request, User $user)
    {
        $this->users->delete($this->actor($request), $user);

        return response()->json(null, 204);
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

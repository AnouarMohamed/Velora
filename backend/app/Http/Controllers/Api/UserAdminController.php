<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\Users\UserWriteService;
use Illuminate\Http\Request;

class UserAdminController extends Controller
{
    public function __construct(private readonly UserWriteService $users) {}

    public function index(Request $request)
    {
        $q = User::query()->orderBy('created_at', 'desc');
        if ($role = $request->query('role')) {
            $q->where('role', $role);
        }

        return response()->json($q->paginate(30));
    }

    public function organizers()
    {
        $users = User::query()
            ->where('role', User::ROLE_ORGANIZER)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->users->create($request->validated());

        return response()->json($user, 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        return response()->json($this->users->update($user, $request->validated()));
    }

    public function destroy(Request $request, User $user)
    {
        $this->users->delete($this->actor($request), $user);

        return response()->json(null, 204);
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

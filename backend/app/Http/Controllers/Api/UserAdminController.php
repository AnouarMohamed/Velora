<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use MongoDB\Driver\Exception\BulkWriteException;

class UserAdminController extends Controller
{
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_ORGANIZER,
                User::ROLE_PARTICIPANT,
                User::ROLE_CLIENT,
            ])],
        ]);

        if (User::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Cette adresse e-mail est déjà utilisée.'],
            ]);
        }

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ]);
        } catch (BulkWriteException $e) {
            // Handle MongoDB duplicate key error (race condition)
            if (str_contains($e->getMessage(), 'duplicate key') || str_contains($e->getMessage(), 'E11000')) {
                throw ValidationException::withMessages([
                    'email' => ['Cette adresse e-mail est déjà utilisée.'],
                ]);
            }
            throw $e;
        }

        NotificationService::userRegistered($user);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email'],
            'password' => ['nullable', Password::defaults()],
            'role' => ['sometimes', Rule::in([
                User::ROLE_ADMIN,
                User::ROLE_ORGANIZER,
                User::ROLE_PARTICIPANT,
                User::ROLE_CLIENT,
            ])],
        ]);

        if (
            isset($data['email'])
            && User::query()->where('email', $data['email'])->whereKeyNot($user->id)->exists()
        ) {
            throw ValidationException::withMessages([
                'email' => ['Cette adresse e-mail est déjà utilisée.'],
            ]);
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        try {
            $user->update($data);
        } catch (BulkWriteException $e) {
            // Handle MongoDB duplicate key error (race condition)
            if (str_contains($e->getMessage(), 'duplicate key') || str_contains($e->getMessage(), 'E11000')) {
                throw ValidationException::withMessages([
                    'email' => ['Cette adresse e-mail est déjà utilisée.'],
                ]);
            }
            throw $e;
        }

        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Impossible de supprimer votre propre compte.'], 422);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}

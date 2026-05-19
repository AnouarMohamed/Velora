<?php

namespace App\Services\Users;

use App\Exceptions\UserManagementException;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use MongoDB\Driver\Exception\BulkWriteException;

class UserWriteService
{
    /** @param array{name: string, email: string, password: string, role: string} $data */
    public function create(array $data): User
    {
        $this->ensureEmailIsAvailable($data['email']);

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
            ]);
        } catch (BulkWriteException $exception) {
            $this->throwDuplicateEmailIfNeeded($exception);

            throw $exception;
        }

        NotificationService::userRegistered($user);

        return $user;
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        if (isset($data['email'])) {
            $this->ensureEmailIsAvailable((string) $data['email'], $user);
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make((string) $data['password']);
        } else {
            unset($data['password']);
        }

        try {
            $user->update($data);
        } catch (BulkWriteException $exception) {
            $this->throwDuplicateEmailIfNeeded($exception);

            throw $exception;
        }

        return $user->fresh() ?? $user;
    }

    public function delete(User $actor, User $user): void
    {
        if ((string) $user->getKey() === (string) $actor->getKey()) {
            throw new UserManagementException('Impossible de supprimer votre propre compte.');
        }

        $user->delete();
    }

    private function ensureEmailIsAvailable(string $email, ?User $except = null): void
    {
        $query = User::query()->where('email', $email);

        if ($except) {
            $query->whereKeyNot($except->getKey());
        }

        if ($query->exists()) {
            $this->throwDuplicateEmailValidation();
        }
    }

    private function throwDuplicateEmailIfNeeded(BulkWriteException $exception): void
    {
        if (str_contains($exception->getMessage(), 'duplicate key') || str_contains($exception->getMessage(), 'E11000')) {
            $this->throwDuplicateEmailValidation();
        }
    }

    private function throwDuplicateEmailValidation(): void
    {
        throw ValidationException::withMessages([
            'email' => ['Cette adresse e-mail est déjà utilisée.'],
        ]);
    }
}

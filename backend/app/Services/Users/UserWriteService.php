<?php

namespace App\Services\Users;

use App\Exceptions\UserManagementException;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Service for managing user creation, updates, and deletion.
 *
 * It handles password hashing, email uniqueness validation, and MongoDB-specific duplicate key exceptions.
 */
class UserWriteService
{
    /**
     * Creates a new user in the system.
     *
     * @param  array{name: string, email: string, password: string, role: string}  $data
     * @return User The newly created user instance.
     *
     * @throws ValidationException If the email is already taken.
     * @throws BulkWriteException If a database-level race condition occurs during creation.
     */
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

    /**
     * Updates an existing user's profile.
     *
     * @param  User  $user  The user instance to update.
     * @param  array<string, mixed>  $data  The data to update (may include name, email, password, etc.).
     * @return User The updated user instance.
     *
     * @throws ValidationException If the new email is already taken.
     */
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

    /**
     * Deletes a user account.
     *
     * @param  User  $actor  The user performing the deletion.
     * @param  User  $user  The user to be deleted.
     *
     * @throws UserManagementException If a user tries to delete their own account.
     */
    public function delete(User $actor, User $user): void
    {
        if ((string) $user->getKey() === (string) $actor->getKey()) {
            throw new UserManagementException('Impossible de supprimer votre propre compte.');
        }

        $user->delete();
    }

    /**
     * Checks if an email is available in the database.
     *
     * @param  string  $email  The email to check.
     * @param  User|null  $except  Optional user to exclude from the check (used during updates).
     *
     * @throws ValidationException
     */
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

    /**
     * Inspects a MongoDB exception for duplicate key errors.
     *
     * @throws ValidationException
     */
    private function throwDuplicateEmailIfNeeded(BulkWriteException $exception): void
    {
        if (str_contains($exception->getMessage(), 'duplicate key') || str_contains($exception->getMessage(), 'E11000')) {
            $this->throwDuplicateEmailValidation();
        }
    }

    /**
     * Throws a standard Laravel ValidationException for duplicate email.
     *
     * @throws ValidationException
     */
    private function throwDuplicateEmailValidation(): void
    {
        throw ValidationException::withMessages([
            'email' => ['Cette adresse e-mail est déjà utilisée.'],
        ]);
    }
}

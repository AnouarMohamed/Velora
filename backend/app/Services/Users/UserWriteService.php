<?php

namespace App\Services\Users;

use App\Exceptions\UserManagementException;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Service pour la gestion de la création, de la mise à jour et de la suppression des utilisateurs.
 *
 * Il gère le hachage des mots de passe, la validation de l'unicité des adresses e-mail et les exceptions de clés dupliquées spécifiques à MongoDB.
 */
class UserWriteService
{
    /**
     * Crée un nouvel utilisateur dans le système.
     *
     * @param  array{name: string, email: string, password: string, role: string}  $data
     * @return User L'instance de l'utilisateur nouvellement créé.
     *
     * @throws ValidationException Si l'adresse e-mail est déjà utilisée.
     * @throws BulkWriteException Si une condition de concurrence au niveau de la base de données survient lors de la création.
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
     * Met à jour le profil d'un utilisateur existant.
     *
     * @param  User  $user  L'instance de l'utilisateur à mettre à jour.
     * @param  array<string, mixed>  $data  Les données à mettre à jour (peuvent inclure le nom, l'e-mail, le mot de passe, etc.).
     * @return User L'instance de l'utilisateur mise à jour.
     *
     * @throws ValidationException Si la nouvelle adresse e-mail est déjà utilisée.
     */
    public function update(User $user, array $data): User
    {
        $email = $data['email'] ?? null;
        if (is_scalar($email)) {
            $this->ensureEmailIsAvailable((string) $email, $user);
        }

        $password = $data['password'] ?? null;
        if (is_string($password) && $password !== '') {
            $data['password'] = Hash::make($password);
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
     * Supprime un compte utilisateur.
     *
     * @param  User  $actor  L'utilisateur effectuant la suppression.
     * @param  User  $user  L'utilisateur à supprimer.
     *
     * @throws UserManagementException Si un utilisateur tente de supprimer son propre compte.
     */
    public function delete(User $actor, User $user): void
    {
        if ($this->modelKey($user) === $this->modelKey($actor)) {
            throw new UserManagementException('Impossible de supprimer votre propre compte.');
        }

        $user->delete();
    }

    private function modelKey(User $user): string
    {
        $key = $user->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Vérifie si une adresse e-mail est disponible dans la base de données.
     *
     * @param  string  $email  L'adresse e-mail à vérifier.
     * @param  User|null  $except  Utilisateur optionnel à exclure de la vérification (utilisé lors des mises à jour).
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
     * Inspecte une exception MongoDB pour les erreurs de clés dupliquées.
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
     * Lance une ValidationException standard de Laravel pour un e-mail dupliqué.
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

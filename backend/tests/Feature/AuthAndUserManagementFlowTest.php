<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class AuthAndUserManagementFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_participant_registers_and_receives_token(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->postJson('/api/register', [
            'name' => 'Participant User',
            'email' => 'participant@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_PARTICIPANT,
        ])
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('user.email', 'participant@example.com')
            ->assertJsonPath('user.role', User::ROLE_PARTICIPANT);

        $user = User::query()->where('email', 'participant@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertTrue(AppNotification::query()
            ->where('user_id', $admin->id)
            ->where('type', 'admin_user_registered')
            ->exists());
    }

    public function test_registration_rejects_duplicate_email_and_admin_role(): void
    {
        $this->user(User::ROLE_PARTICIPANT, ['email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Duplicate User',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_CLIENT,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Cette adresse e-mail est déjà utilisée.');

        $this->postJson('/api/register', [
            'name' => 'Invalid Role',
            'email' => 'admin-register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => User::ROLE_ADMIN,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_login_returns_token_and_rejects_invalid_credentials(): void
    {
        $this->user(User::ROLE_CLIENT, [
            'email' => 'client-login@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'client-login@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']])
            ->assertJsonPath('user.email', 'client-login@example.com');

        $this->postJson('/api/login', [
            'email' => 'client-login@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Identifiants invalides.');
    }

    public function test_admin_creates_updates_and_deletes_users(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/admin/users', [
            'name' => 'Organizer User',
            'email' => 'organizer-admin@example.com',
            'password' => 'password123',
            'role' => User::ROLE_ORGANIZER,
        ])
            ->assertCreated()
            ->assertJsonPath('email', 'organizer-admin@example.com')
            ->assertJsonPath('role', User::ROLE_ORGANIZER);

        $userId = $created->json('id');
        $this->assertNotNull($userId);

        $this->patchJson("/api/admin/users/{$userId}", [
            'name' => 'Updated Organizer',
            'email' => 'updated-organizer@example.com',
            'password' => 'newpassword123',
            'role' => User::ROLE_ADMIN,
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Organizer')
            ->assertJsonPath('email', 'updated-organizer@example.com')
            ->assertJsonPath('role', User::ROLE_ADMIN);

        $updatedUser = User::query()->findOrFail($userId);
        $this->assertTrue(Hash::check('newpassword123', $updatedUser->password));

        $this->deleteJson("/api/admin/users/{$userId}")
            ->assertNoContent();

        $this->assertNull(User::query()->find($userId));
    }

    public function test_admin_user_management_rejects_duplicate_email_and_self_delete(): void
    {
        $admin = $this->user(User::ROLE_ADMIN, ['email' => 'admin@example.com']);
        $existing = $this->user(User::ROLE_CLIENT, ['email' => 'existing@example.com']);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'Duplicate Admin Create',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role' => User::ROLE_PARTICIPANT,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Cette adresse e-mail est déjà utilisée.');

        $this->patchJson("/api/admin/users/{$existing->id}", [
            'email' => 'admin@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Cette adresse e-mail est déjà utilisée.');

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Impossible de supprimer votre propre compte.');
    }

    public function test_admin_organizers_endpoint_returns_only_organizers_by_name(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $zOrganizer = $this->user(User::ROLE_ORGANIZER, ['name' => 'Z Organizer']);
        $aOrganizer = $this->user(User::ROLE_ORGANIZER, ['name' => 'A Organizer']);
        $this->user(User::ROLE_CLIENT, ['name' => 'Client User']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/organizers')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $aOrganizer->id)
            ->assertJsonPath('1.id', $zOrganizer->id);
    }

    /** @param array<string, mixed> $overrides */
    private function user(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $overrides));
    }
}

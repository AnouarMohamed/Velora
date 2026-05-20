<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class QueryValidationTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_admin_user_index_rejects_invalid_role_filter(): void
    {
        Sanctum::actingAs($this->user(User::ROLE_ADMIN));

        $this->getJson('/api/admin/users?role=superadmin')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_admin_event_request_index_rejects_invalid_status_filter(): void
    {
        Sanctum::actingAs($this->user(User::ROLE_ADMIN));

        $this->getJson('/api/admin/event-requests?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_event_indexes_reject_oversized_search_filter(): void
    {
        Sanctum::actingAs($this->user(User::ROLE_ADMIN));

        $query = str_repeat('a', 121);

        $this->getJson('/api/admin/events?q='.$query)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');

        $this->getJson('/api/events/browse?q='.$query)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }
}

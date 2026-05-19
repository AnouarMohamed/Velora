<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class EventManagementFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_organizer_created_event_stays_draft_even_when_published_status_is_requested(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        Sanctum::actingAs($organizer);

        $this->postJson('/api/organizer/events', $this->eventPayload([
            'status' => Event::STATUS_PUBLISHED,
        ]))
            ->assertCreated()
            ->assertJsonPath('status', Event::STATUS_DRAFT)
            ->assertJsonPath('organizer_id', $organizer->id)
            ->assertJsonPath('created_by', $organizer->id);
    }

    public function test_admin_can_create_published_event(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/organizer/events', $this->eventPayload([
            'status' => Event::STATUS_PUBLISHED,
        ]))
            ->assertCreated()
            ->assertJsonPath('status', Event::STATUS_PUBLISHED)
            ->assertJsonPath('organizer_id', $admin->id);
    }

    public function test_organizer_cannot_publish_event_directly_on_update(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $event = $this->eventFor($organizer);

        Sanctum::actingAs($organizer);

        $this->patchJson("/api/organizer/events/{$event->id}", [
            'status' => Event::STATUS_PUBLISHED,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Seul un administrateur peut publier l’événement. Envoyez une demande de publication.');

        $this->assertSame(Event::STATUS_DRAFT, $event->fresh()->status);
    }

    public function test_organizer_requests_publication_and_admin_approves_it(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $event = $this->eventFor($organizer);

        Sanctum::actingAs($organizer);

        $this->postJson("/api/organizer/events/{$event->id}/request-publication")
            ->assertOk()
            ->assertJsonPath('status', Event::STATUS_PENDING_PUBLICATION);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/events/{$event->id}/approve-publication")
            ->assertOk()
            ->assertJsonPath('status', Event::STATUS_PUBLISHED);

        $this->assertSame(Event::STATUS_PUBLISHED, $event->fresh()->status);
    }

    public function test_capacity_cannot_be_reduced_below_registered_count(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $event = $this->eventFor($organizer, [
            'capacity' => 10,
            'registered_count' => 5,
        ]);

        Sanctum::actingAs($organizer);

        $this->patchJson("/api/organizer/events/{$event->id}/capacity", [
            'capacity' => 4,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La capacité ne peut pas être inférieure au nombre d’inscrits.');

        $this->assertSame(10, (int) $event->fresh()->capacity);
    }

    public function test_admin_assigns_event_to_organizer(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $event = $this->eventFor($admin);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/events/{$event->id}/assign-organizer", [
            'organizer_id' => $organizer->id,
        ])
            ->assertOk()
            ->assertJsonPath('organizer_id', $organizer->id)
            ->assertJsonPath('organizer.id', $organizer->id);

        $this->assertSame($organizer->id, $event->fresh()->organizer_id);
    }

    public function test_other_organizer_cannot_update_someone_elses_event(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $other = User::factory()->create(['role' => User::ROLE_ORGANIZER]);
        $event = $this->eventFor($owner);

        Sanctum::actingAs($other);

        $this->patchJson("/api/organizer/events/{$event->id}", [
            'title' => 'Updated by another organizer',
        ])->assertForbidden();

        $this->assertNotSame('Updated by another organizer', $event->fresh()->title);
    }

    public function test_invalid_base64_image_is_rejected_with_existing_error_shape(): void
    {
        $organizer = User::factory()->create(['role' => User::ROLE_ORGANIZER]);

        Sanctum::actingAs($organizer);

        $this->postJson('/api/organizer/events', $this->eventPayload([
            'image_data' => 'not-valid-base64!',
            'image_mime' => 'image/png',
        ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Image invalide.')
            ->assertJsonStructure(['message', 'errors' => ['image_data']]);
    }

    /** @param array<string, mixed> $overrides */
    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Security Conference',
            'description' => 'A focused security event.',
            'location' => 'Casablanca',
            'room' => 'A1',
            'start_at' => Carbon::now()->addDays(12)->toIso8601String(),
            'end_at' => Carbon::now()->addDays(12)->addHours(3)->toIso8601String(),
            'capacity' => 120,
            'ticket_price' => 30,
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    private function eventFor(User $user, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'organizer_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Managed Event',
            'description' => 'Managed event description.',
            'location' => 'Casablanca',
            'room' => 'B2',
            'start_at' => Carbon::now()->addDays(15),
            'end_at' => Carbon::now()->addDays(15)->addHours(4),
            'capacity' => 100,
            'registered_count' => 0,
            'ticket_price' => 20,
            'status' => Event::STATUS_DRAFT,
        ], $overrides));
    }
}

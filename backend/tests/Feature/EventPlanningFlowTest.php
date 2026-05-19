<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventActivity;
use App\Models\EventTask;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class EventPlanningFlowTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_organizer_manages_tasks_for_owned_event(): void
    {
        $organizer = $this->user(User::ROLE_ORGANIZER);
        $event = $this->eventFor($organizer);

        Sanctum::actingAs($organizer);

        $created = $this->postJson("/api/organizer/events/{$event->id}/tasks", [
            'title' => 'Confirm venue access',
            'description' => 'Call building security.',
            'due_at' => Carbon::now()->addDays(3)->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('event_id', $event->id)
            ->assertJsonPath('title', 'Confirm venue access')
            ->assertJsonPath('is_done', false);

        $taskId = $created->json('id');
        $this->assertNotNull($taskId);

        $this->patchJson("/api/organizer/events/{$event->id}/tasks/{$taskId}", [
            'title' => 'Confirm venue and badges',
            'is_done' => true,
        ])
            ->assertOk()
            ->assertJsonPath('title', 'Confirm venue and badges')
            ->assertJsonPath('is_done', true);

        $this->getJson("/api/organizer/events/{$event->id}/tasks")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $taskId);

        $this->deleteJson("/api/organizer/events/{$event->id}/tasks/{$taskId}")
            ->assertNoContent();

        $this->assertSame(0, EventTask::query()->count());
    }

    public function test_other_organizer_cannot_manage_event_tasks(): void
    {
        $owner = $this->user(User::ROLE_ORGANIZER);
        $other = $this->user(User::ROLE_ORGANIZER);
        $event = $this->eventFor($owner);
        $task = $this->taskFor($event);

        Sanctum::actingAs($other);

        $this->postJson("/api/organizer/events/{$event->id}/tasks", [
            'title' => 'Unauthorized task',
        ])->assertForbidden();

        $this->patchJson("/api/organizer/events/{$event->id}/tasks/{$task->id}", [
            'is_done' => true,
        ])->assertForbidden();

        $this->assertFalse((bool) $task->fresh()->is_done);
    }

    public function test_task_route_rejects_task_from_another_event(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $firstEvent = $this->eventFor($admin);
        $secondEvent = $this->eventFor($admin);
        $task = $this->taskFor($secondEvent);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/events/{$firstEvent->id}/tasks/{$task->id}", [
            'is_done' => true,
        ])->assertNotFound();

        $this->assertFalse((bool) $task->fresh()->is_done);
    }

    public function test_admin_manages_event_activities_with_stable_ordering(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $event = $this->eventFor($admin);

        Sanctum::actingAs($admin);

        $later = $this->postJson("/api/admin/events/{$event->id}/activities", [
            'title' => 'Closing panel',
            'starts_at' => Carbon::now()->addDays(8)->hour(16)->toIso8601String(),
            'ends_at' => Carbon::now()->addDays(8)->hour(17)->toIso8601String(),
            'sort_order' => 20,
        ])->assertCreated();

        $earlier = $this->postJson("/api/admin/events/{$event->id}/activities", [
            'title' => 'Opening keynote',
            'starts_at' => Carbon::now()->addDays(8)->hour(9)->toIso8601String(),
            'ends_at' => Carbon::now()->addDays(8)->hour(10)->toIso8601String(),
            'sort_order' => 10,
        ])->assertCreated();

        $this->getJson("/api/admin/events/{$event->id}/activities")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $earlier->json('id'))
            ->assertJsonPath('1.id', $later->json('id'));

        $this->patchJson("/api/admin/events/{$event->id}/activities/{$later->json('id')}", [
            'title' => 'Closing panel and Q&A',
            'sort_order' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('title', 'Closing panel and Q&A')
            ->assertJsonPath('sort_order', 30);
    }

    public function test_activity_end_must_not_be_before_start(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $event = $this->eventFor($admin);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/events/{$event->id}/activities", [
            'title' => 'Broken slot',
            'starts_at' => Carbon::now()->addDays(8)->hour(12)->toIso8601String(),
            'ends_at' => Carbon::now()->addDays(8)->hour(11)->toIso8601String(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');

        $this->assertSame(0, EventActivity::query()->count());
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** @param array<string, mixed> $overrides */
    private function eventFor(User $user, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'organizer_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Planning Event',
            'description' => 'A managed event with agenda and tasks.',
            'location' => 'Casablanca',
            'room' => 'Planning Room',
            'start_at' => Carbon::now()->addDays(8),
            'end_at' => Carbon::now()->addDays(8)->addHours(8),
            'capacity' => 100,
            'registered_count' => 0,
            'ticket_price' => 20,
            'status' => Event::STATUS_DRAFT,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function taskFor(Event $event, array $overrides = []): EventTask
    {
        return EventTask::create(array_merge([
            'event_id' => $event->id,
            'title' => 'Existing task',
            'description' => null,
            'is_done' => false,
            'due_at' => Carbon::now()->addDays(2),
        ], $overrides));
    }
}

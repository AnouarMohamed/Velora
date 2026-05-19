<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Collection;

return new class extends Migration
{
    /** @var list<string> */
    private array $collections = [
        'users',
        'personal_access_tokens',
        'event_requests',
        'events',
        'event_tasks',
        'event_activities',
        'registrations',
        'payments',
        'feedbacks',
        'app_notifications',
    ];

    public function up(): void
    {
        $this->index('users', ['email' => 1], ['name' => 'users_email_unique', 'unique' => true]);
        $this->index('users', ['role' => 1], ['name' => 'users_role_idx']);

        $this->index('personal_access_tokens', ['token' => 1], ['name' => 'tokens_token_unique', 'unique' => true]);
        $this->index('personal_access_tokens', ['tokenable_type' => 1, 'tokenable_id' => 1], ['name' => 'tokens_tokenable_idx']);
        $this->index('personal_access_tokens', ['expires_at' => 1], ['name' => 'tokens_expires_at_idx']);

        $this->index('event_requests', ['contact_email' => 1, 'status' => 1], ['name' => 'event_requests_contact_status_idx']);
        $this->index('event_requests', ['status' => 1, 'created_at' => -1], ['name' => 'event_requests_status_created_idx']);

        $this->index('events', ['event_request_id' => 1], ['name' => 'events_event_request_unique', 'unique' => true, 'sparse' => true]);
        $this->index('events', ['status' => 1, 'start_at' => 1], ['name' => 'events_status_start_idx']);
        $this->index('events', ['organizer_id' => 1, 'status' => 1], ['name' => 'events_organizer_status_idx']);
        $this->index('events', ['created_by' => 1, 'status' => 1], ['name' => 'events_creator_status_idx']);

        $this->index('event_tasks', ['event_id' => 1, 'due_at' => 1], ['name' => 'event_tasks_event_due_idx']);
        $this->index('event_activities', ['event_id' => 1, 'sort_order' => 1, 'starts_at' => 1], ['name' => 'event_activities_event_order_idx']);

        $this->index('registrations', ['event_id' => 1, 'user_id' => 1], ['name' => 'registrations_event_user_unique', 'unique' => true]);
        $this->index('registrations', ['user_id' => 1, 'payment_status' => 1], ['name' => 'registrations_user_payment_idx']);
        $this->index('registrations', ['event_id' => 1, 'payment_status' => 1], ['name' => 'registrations_event_payment_idx']);
        $this->index('registrations', ['ticket_code' => 1], ['name' => 'registrations_ticket_code_unique', 'unique' => true]);

        $this->index('payments', ['registration_id' => 1], ['name' => 'payments_registration_idx']);
        $this->index('payments', ['status' => 1], ['name' => 'payments_status_idx']);

        $this->index('feedbacks', ['event_id' => 1, 'user_id' => 1], ['name' => 'feedbacks_event_user_unique', 'unique' => true]);
        $this->index('feedbacks', ['event_id' => 1, 'status' => 1], ['name' => 'feedbacks_event_status_idx']);

        $this->index('app_notifications', ['user_id' => 1, 'read_at' => 1, 'created_at' => -1], ['name' => 'notifications_user_read_created_idx']);
    }

    public function down(): void
    {
        // Drop only the indexes created in up(), not the collections themselves
        $this->dropIndex('users', 'users_email_unique');
        $this->dropIndex('users', 'users_role_idx');

        $this->dropIndex('personal_access_tokens', 'tokens_token_unique');
        $this->dropIndex('personal_access_tokens', 'tokens_tokenable_idx');
        $this->dropIndex('personal_access_tokens', 'tokens_expires_at_idx');

        $this->dropIndex('event_requests', 'event_requests_contact_status_idx');
        $this->dropIndex('event_requests', 'event_requests_status_created_idx');

        $this->dropIndex('events', 'events_event_request_unique');
        $this->dropIndex('events', 'events_status_start_idx');
        $this->dropIndex('events', 'events_organizer_status_idx');
        $this->dropIndex('events', 'events_creator_status_idx');

        $this->dropIndex('event_tasks', 'event_tasks_event_due_idx');
        $this->dropIndex('event_activities', 'event_activities_event_order_idx');

        $this->dropIndex('registrations', 'registrations_event_user_unique');
        $this->dropIndex('registrations', 'registrations_user_payment_idx');
        $this->dropIndex('registrations', 'registrations_event_payment_idx');
        $this->dropIndex('registrations', 'registrations_ticket_code_unique');

        $this->dropIndex('payments', 'payments_registration_idx');
        $this->dropIndex('payments', 'payments_status_idx');

        $this->dropIndex('feedbacks', 'feedbacks_event_user_unique');
        $this->dropIndex('feedbacks', 'feedbacks_event_status_idx');

        $this->dropIndex('app_notifications', 'notifications_user_read_created_idx');
    }

    /** @param array<string, int> $keys @param array<string, mixed> $options */
    private function index(string $collection, array $keys, array $options): void
    {
        $this->collection($collection)->createIndex($keys, $options);
    }

    private function dropIndex(string $collection, string $indexName): void
    {
        $this->collection($collection)->dropIndex($indexName);
    }

    private function collection(string $name): Collection
    {
        return DB::connection('mongodb')->getDatabase()->selectCollection($name);
    }
};

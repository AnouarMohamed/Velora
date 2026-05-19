<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class MongoIndexTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_required_mongo_indexes_are_created(): void
    {
        $expectedIndexes = [
            'users' => ['users_email_unique', 'users_role_idx'],
            'personal_access_tokens' => ['tokens_token_unique', 'tokens_tokenable_idx', 'tokens_expires_at_idx'],
            'event_requests' => ['event_requests_contact_status_idx', 'event_requests_status_created_idx'],
            'events' => ['events_event_request_unique', 'events_status_start_idx', 'events_organizer_status_idx', 'events_creator_status_idx'],
            'event_tasks' => ['event_tasks_event_due_idx'],
            'event_activities' => ['event_activities_event_order_idx'],
            'registrations' => [
                'registrations_event_user_unique',
                'registrations_user_payment_idx',
                'registrations_event_payment_idx',
                'registrations_ticket_code_unique',
            ],
            'payments' => ['payments_registration_idx', 'payments_status_idx'],
            'feedbacks' => ['feedbacks_event_user_unique', 'feedbacks_event_status_idx'],
            'app_notifications' => ['notifications_user_read_created_idx'],
        ];

        foreach ($expectedIndexes as $collection => $indexes) {
            $actualIndexes = $this->indexNames($collection);

            foreach ($indexes as $index) {
                $this->assertContains($index, $actualIndexes, "Missing {$collection}.{$index}");
            }
        }
    }

    /** @return list<string> */
    private function indexNames(string $collection): array
    {
        $indexes = DB::connection('mongodb')
            ->getDatabase()
            ->selectCollection($collection)
            ->listIndexes();

        return array_values(array_map(
            fn ($index): string => $index->getName(),
            iterator_to_array($indexes),
        ));
    }
}

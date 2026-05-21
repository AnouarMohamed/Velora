<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait RefreshMongoDatabase
{
    /** @var list<string> */
    private array $mongoCollections = [
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->refreshMongoDatabase();
    }

    protected function refreshMongoDatabase(): void
    {
        Cache::flush();

        $database = DB::connection('mongodb')->getDatabase();

        if (! MongoDatabaseState::$migrated) {
            foreach ($database->listCollectionNames() as $collection) {
                $database->dropCollection($collection);
            }

            $this->artisan('migrate', ['--force' => true])->run();
            MongoDatabaseState::$migrated = true;
        }

        foreach ($this->mongoCollections as $collection) {
            $database->selectCollection($collection)->deleteMany([]);
        }
    }
}

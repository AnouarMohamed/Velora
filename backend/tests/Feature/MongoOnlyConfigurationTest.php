<?php

namespace Tests\Feature;

use Tests\TestCase;

class MongoOnlyConfigurationTest extends TestCase
{
    public function test_backend_exposes_health_endpoint(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('services.mongodb.status', 'ok')
            ->assertJsonPath('services.redis.status', 'ok')
            ->assertJsonStructure([
                'status',
                'checked_at',
                'services' => [
                    'mongodb' => ['status'],
                    'redis' => ['status'],
                ],
            ]);
    }

    public function test_database_configuration_is_mongo_only(): void
    {
        $this->assertSame('mongodb', config('database.default'));
        $this->assertSame(['mongodb'], array_keys(config('database.connections')));
        $this->assertSame('redis', config('cache.default'));
        $this->assertSame('redis', config('queue.default'));
        $this->assertSame('redis', config('session.driver'));
    }

    public function test_redis_queue_timeout_is_safe_for_published_event_fan_out_job(): void
    {
        $this->assertSame('redis', config('queue.connections.redis.driver'));
        $this->assertGreaterThan(120, config('queue.connections.redis.retry_after'));
    }
}

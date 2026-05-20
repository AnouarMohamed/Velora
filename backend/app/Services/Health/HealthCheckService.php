<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use MongoDB\Laravel\Connection as MongoConnection;
use RuntimeException;
use Throwable;

/**
 * Builds the public API health report used by Docker and manual smoke checks.
 *
 * This endpoint checks real runtime dependencies instead of returning a static "ok".
 * A degraded response is still JSON so local tooling and frontend checks can show which
 * dependency is down without reading container logs first.
 */
class HealthCheckService
{
    /**
     * @return array{
     *     status: 'ok'|'degraded',
     *     checked_at: string,
     *     services: array<string, array{status: 'ok'|'down', error?: string}>
     * }
     */
    public function report(): array
    {
        // Keep dependency names stable because the OpenAPI contract documents these keys.
        $services = [
            'mongodb' => $this->checkMongo(),
            'redis' => $this->checkRedis(),
        ];

        return [
            'status' => $this->allHealthy($services) ? 'ok' : 'degraded',
            'checked_at' => now()->toIso8601String(),
            'services' => $services,
        ];
    }

    /**
     * @param  array<string, array{status: 'ok'|'down', error?: string}>  $services
     */
    public function allHealthy(array $services): bool
    {
        foreach ($services as $service) {
            if ($service['status'] !== 'ok') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{status: 'ok'|'down', error?: string}
     */
    private function checkMongo(): array
    {
        try {
            $connection = DB::connection('mongodb');

            // A wrong driver here means the app is no longer running in Mongo-only mode.
            if (! $connection instanceof MongoConnection) {
                throw new RuntimeException('MongoDB connection is not using the MongoDB driver.');
            }

            // ping is cheap and verifies the current connection can talk to the Mongo server.
            $connection->getDatabase()->command(['ping' => 1])->toArray();

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return $this->down($exception);
        }
    }

    /**
     * @return array{status: 'ok'|'down', error?: string}
     */
    private function checkRedis(): array
    {
        try {
            // Redis backs cache, queues, rate limiting, and sessions in the local stack.
            Redis::connection()->ping();

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return $this->down($exception);
        }
    }

    /**
     * @return array{status: 'down', error: string}
     */
    private function down(Throwable $exception): array
    {
        // Include the dependency error in local/dev health responses to speed up troubleshooting.
        return [
            'status' => 'down',
            'error' => $exception->getMessage(),
        ];
    }
}

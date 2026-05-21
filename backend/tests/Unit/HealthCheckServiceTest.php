<?php

namespace Tests\Unit;

use App\Services\Health\HealthCheckService;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

class HealthCheckServiceTest extends TestCase
{
    public function test_dependency_errors_are_sanitized_when_debug_is_disabled(): void
    {
        config(['app.debug' => false]);

        $method = new ReflectionMethod(HealthCheckService::class, 'down');
        $method->setAccessible(true);

        $report = $method->invoke(
            new HealthCheckService,
            new RuntimeException('mongodb://secret-user:secret-pass@mongodb:27017 failed'),
        );

        $this->assertSame([
            'status' => 'down',
            'error' => 'Dépendance indisponible.',
        ], $report);
    }
}

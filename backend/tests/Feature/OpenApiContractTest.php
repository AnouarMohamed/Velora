<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiContractTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $document;

    protected function setUp(): void
    {
        parent::setUp();

        $document = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
        $this->assertIsArray($document);
        $this->document = $document;
    }

    public function test_openapi_document_parses_and_declares_expected_api_version(): void
    {
        $this->assertSame('3.1.0', data_get($this->document, 'openapi'));
        $this->assertSame('API Backend VELORA', data_get($this->document, 'info.title'));
        $this->assertIsArray(data_get($this->document, 'paths'));
        $this->assertIsArray(data_get($this->document, 'components.schemas'));
    }

    public function test_health_endpoint_is_public_and_tagged_correctly(): void
    {
        $this->assertSame(['Health'], data_get($this->document, 'paths./health.get.tags'));
        $this->assertSame([], data_get($this->document, 'paths./health.get.security'));
    }

    public function test_notifications_endpoint_documents_real_paginated_shape(): void
    {
        $this->assertSame(
            '#/components/schemas/NotificationPage',
            data_get($this->document, 'paths./notifications.get.responses.200.content.application/json.schema.$ref'),
        );

        $properties = data_get($this->document, 'components.schemas.Notification.properties');
        $this->assertIsArray($properties);
        $this->assertArrayHasKey('message', $properties);
        $this->assertArrayNotHasKey('body', $properties);

        $this->assertSame(
            ['data', 'unread_count', 'meta'],
            data_get($this->document, 'components.schemas.NotificationPage.required'),
        );
    }
}

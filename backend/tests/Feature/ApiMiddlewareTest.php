<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiMiddlewareTest extends TestCase
{
    public function test_api_responses_include_security_headers_and_request_id(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'")
            ->assertHeader('X-Request-Id');
    }

    public function test_api_reuses_safe_inbound_request_id(): void
    {
        $this->withHeader('X-Request-Id', 'frontend-request-123')
            ->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-Request-Id', 'frontend-request-123');
    }

    public function test_api_replaces_invalid_inbound_request_id(): void
    {
        $response = $this->withHeader('X-Request-Id', "bad\nheader")
            ->getJson('/api/health')
            ->assertOk();

        $this->assertNotSame("bad\nheader", $response->headers->get('X-Request-Id'));
        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
    }

    public function test_api_error_responses_include_hardening_headers(): void
    {
        $this->withHeader('X-Request-Id', 'unauthenticated-check-1')
            ->getJson('/api/user')
            ->assertUnauthorized()
            ->assertHeader('X-Request-Id', 'unauthenticated-check-1')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
    }

    public function test_api_errors_return_json_without_accept_header(): void
    {
        $this->withHeader('X-Request-Id', 'plain-request-1')
            ->get('/api/user')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('X-Request-Id', 'plain-request-1')
            ->assertJsonPath('message', 'Unauthenticated.');
    }
}

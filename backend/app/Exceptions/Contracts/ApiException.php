<?php

namespace App\Exceptions\Contracts;

/**
 * Interface for exceptions that should be rendered as JSON API responses.
 *
 * Exceptions implementing this contract provide their own HTTP status codes
 * and response payloads, allowing for consistent error reporting across the API.
 */
interface ApiException
{
    /**
     * Get the HTTP status code for the error response.
     */
    public function statusCode(): int;

    /**
     * Get the data structure to be returned in the JSON response body.
     *
     * @return array<string, mixed>
     */
    public function toResponsePayload(): array;
}

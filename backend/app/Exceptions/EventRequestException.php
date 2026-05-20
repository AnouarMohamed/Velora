<?php

namespace App\Exceptions;

use App\Exceptions\Contracts\ApiException;
use RuntimeException;

/**
 * Exception thrown for errors related to event requests (proposals).
 *
 * This exception can carry additional context data to help the frontend
 * understand the specific nature of the failure.
 */
class EventRequestException extends RuntimeException implements ApiException
{
    /**
     * @param  string  $message  The error message.
     * @param  int  $status  The HTTP status code.
     * @param  array<string, mixed>  $context  Additional data related to the error.
     */
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Get the HTTP status code for the response.
     */
    public function statusCode(): int
    {
        return $this->status;
    }

    /**
     * Get the response payload, including the error message and any additional context.
     *
     * @return array<string, mixed>
     */
    public function toResponsePayload(): array
    {
        return ['message' => $this->getMessage()] + $this->context;
    }
}

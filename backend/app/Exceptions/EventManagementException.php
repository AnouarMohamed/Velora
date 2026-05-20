<?php

namespace App\Exceptions;

use App\Exceptions\Contracts\ApiException;
use RuntimeException;

/**
 * Exception thrown for general event management errors.
 *
 * Used when performing operations on events that fail business logic checks,
 * such as updating a completed event or invalid status transitions.
 */
class EventManagementException extends RuntimeException implements ApiException
{
    /**
     * @param  string  $message  The error message.
     * @param  int  $status  The HTTP status code.
     */
    public function __construct(
        string $message,
        public readonly int $status = 422,
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
     * Get the response payload representation of the exception.
     *
     * @return array<string, mixed>
     */
    public function toResponsePayload(): array
    {
        return ['message' => $this->getMessage()];
    }
}

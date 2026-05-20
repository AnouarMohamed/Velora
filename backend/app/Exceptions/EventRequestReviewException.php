<?php

namespace App\Exceptions;

use App\Exceptions\Contracts\ApiException;
use RuntimeException;

/**
 * Exception thrown when a failure occurs during the review of an event request.
 *
 * This exception is typically raised when an administrator attempts to approve
 * or reject an event request but violates a business rule (e.g., trying to approve
 * a request that is already processed).
 */
class EventRequestReviewException extends RuntimeException implements ApiException
{
    /**
     * @param  string  $message  The error message.
     * @param  int  $status  The HTTP status code (defaults to 422 Unprocessable Entity).
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

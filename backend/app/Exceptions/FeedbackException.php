<?php

namespace App\Exceptions;

use App\Exceptions\Contracts\ApiException;
use RuntimeException;

/**
 * Exception thrown for errors related to the feedback system.
 *
 * Examples include attempting to submit feedback for an event the user didn't attend,
 * or trying to modify feedback that is already locked.
 */
class FeedbackException extends RuntimeException implements ApiException
{
    /**
     * @param  string  $message  The error message.
     * @param  int  $status  The HTTP status code.
     */
    public function __construct(
        string $message,
        private readonly int $status = 422,
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

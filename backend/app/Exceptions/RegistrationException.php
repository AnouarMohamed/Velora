<?php

namespace App\Exceptions;

use App\Exceptions\Contracts\ApiException;
use App\Models\Registration;
use RuntimeException;

/**
 * Exception thrown for errors related to event registrations.
 *
 * This includes failures during the registration process, payment issues,
 * or violations of registration constraints (e.g., event at full capacity).
 */
class RegistrationException extends RuntimeException implements ApiException
{
    /**
     * @param  string  $message  The error message.
     * @param  int  $status  The HTTP status code.
     * @param  Registration|null  $registration  The registration instance associated with the error, if any.
     */
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly ?Registration $registration = null,
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
     * Get the response payload, optionally including the registration details.
     *
     * @return array<string, mixed>
     */
    public function toResponsePayload(): array
    {
        $payload = ['message' => $this->getMessage()];

        if ($this->registration) {
            $payload['registration'] = $this->registration;
        }

        return $payload;
    }
}

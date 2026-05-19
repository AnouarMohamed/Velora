<?php

namespace App\Exceptions;

use App\Models\Registration;
use RuntimeException;

class RegistrationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly ?Registration $registration = null,
    ) {
        parent::__construct($message);
    }

    /** @return array<string, mixed> */
    public function toResponsePayload(): array
    {
        $payload = ['message' => $this->getMessage()];

        if ($this->registration) {
            $payload['registration'] = $this->registration;
        }

        return $payload;
    }
}

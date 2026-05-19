<?php

namespace App\Exceptions;

use App\Exceptions\Contracts\ApiException;
use App\Models\Registration;
use RuntimeException;

class RegistrationException extends RuntimeException implements ApiException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly ?Registration $registration = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->status;
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

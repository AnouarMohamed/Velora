<?php

namespace App\Exceptions;

use App\Exceptions\Contracts\ApiException;
use RuntimeException;

class EventManagementException extends RuntimeException implements ApiException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
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
        return ['message' => $this->getMessage()];
    }
}

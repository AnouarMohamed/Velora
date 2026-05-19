<?php

namespace App\Exceptions;

use RuntimeException;

class EventManagementException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function toResponsePayload(): array
    {
        return ['message' => $this->getMessage()];
    }
}

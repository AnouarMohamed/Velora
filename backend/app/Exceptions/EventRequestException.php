<?php

namespace App\Exceptions;

use App\Exceptions\Contracts\ApiException;
use RuntimeException;

class EventRequestException extends RuntimeException implements ApiException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly array $context = [],
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
        return ['message' => $this->getMessage()] + $this->context;
    }
}

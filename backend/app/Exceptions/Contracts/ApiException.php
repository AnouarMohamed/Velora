<?php

namespace App\Exceptions\Contracts;

interface ApiException
{
    public function statusCode(): int;

    /** @return array<string, mixed> */
    public function toResponsePayload(): array;
}

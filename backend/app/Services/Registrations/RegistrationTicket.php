<?php

namespace App\Services\Registrations;

readonly class RegistrationTicket
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $filename,
        public array $payload,
    ) {}
}

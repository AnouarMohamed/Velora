<?php

namespace App\Services\Registrations;

/**
 * Data Transfer Object (DTO) representing a registration ticket.
 *
 * This object holds the necessary information to generate or present a ticket
 * to the participant, including the filename for download and the ticket payload.
 */
readonly class RegistrationTicket
{
    /**
     * @param  string  $filename  Suggested filename for the ticket file (e.g., "ticket-123.json").
     * @param  array<string, mixed>  $payload  The ticket data (code, event title, participant name, etc.).
     */
    public function __construct(
        public string $filename,
        public array $payload,
    ) {}
}

<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FanOutPublishedEventNotifications implements ShouldQueue
{
    use Queueable;

    private const CHUNK_SIZE = 500;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $eventId,
        private readonly string $title,
        private readonly string $message,
        private readonly array $data,
    ) {}

    public function handle(): void
    {
        $participantIds = [];

        foreach (User::query()
            ->where('role', User::ROLE_PARTICIPANT)
            ->select(['_id'])
            ->cursor() as $participant) {
            $participantId = $participant->getKey();
            if (! is_int($participantId) && ! is_string($participantId)) {
                continue;
            }

            $participantIds[] = (string) $participantId;

            if (count($participantIds) >= self::CHUNK_SIZE) {
                $this->sendToParticipants($participantIds);
                $participantIds = [];
            }
        }

        $this->sendToParticipants($participantIds);
    }

    /**
     * @param  list<string>  $participantIds
     */
    private function sendToParticipants(array $participantIds): void
    {
        if ($participantIds === []) {
            return;
        }

        NotificationService::send(
            $participantIds,
            'participant_new_event',
            $this->title,
            $this->message,
            $this->data,
        );
    }
}

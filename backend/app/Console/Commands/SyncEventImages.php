<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class SyncEventImages extends Command
{
    protected $signature = 'velora:sync-event-images';

    protected $description = 'Copie image_path des demandes approuvées vers les événements liés';

    public function handle(): int
    {
        $count = 0;

        Event::query()
            ->whereNull('image_path')
            ->whereHas('eventRequest', fn ($q) => $q->whereNotNull('image_path'))
            ->with('eventRequest')
            ->each(function (Event $event) use (&$count) {
                $event->update(['image_path' => $event->eventRequest->image_path]);
                $count++;
            });

        $this->info("{$count} événement(s) mis à jour.");

        return self::SUCCESS;
    }
}

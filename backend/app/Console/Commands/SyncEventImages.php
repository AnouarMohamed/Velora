<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Artisan command to synchronize image paths from approved event requests to their actual event models.
 *
 * This command bridges the gap between the initial client proposal (EventRequest) and the
 * final Event instance, ensuring that the branding images provided by clients are
 * correctly associated with the published events.
 */
class SyncEventImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velora:sync-event-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copies image_path from approved event requests to their linked events if the event lacks an image.';

    /**
     * Execute the console command.
     *
     * Iterates through events that do not have an image path but whose parent
     * request does, and updates the event model accordingly.
     */
    public function handle(): int
    {
        $count = 0;

        // Find events that are missing an image but have a request with one
        Event::query()
            ->whereNull('image_path')
            ->whereHas('eventRequest', fn ($q) => $q->whereNotNull('image_path'))
            ->with('eventRequest')
            ->each(function (Event $event) use (&$count) {
                // Update the event's image path from its request
                $event->update(['image_path' => $event->eventRequest->image_path]);
                $count++;
            });

        $this->info("{$count} événement(s) mis à jour.");

        return self::SUCCESS;
    }
}

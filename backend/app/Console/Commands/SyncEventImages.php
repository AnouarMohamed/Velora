<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventRequest;
use Illuminate\Console\Command;

/**
 * Commande Artisan pour synchroniser les chemins d'images des demandes d'événements approuvées avec leurs modèles d'événements réels.
 *
 * Cette commande comble le fossé entre la proposition initiale du client (EventRequest) et
 * l'instance finale de l'événement (Event), en s'assurant que les images de marque fournies par les clients sont
 * correctement associées aux événements publiés.
 */
class SyncEventImages extends Command
{
    /**
     * Le nom et la signature de la commande de console.
     *
     * @var string
     */
    protected $signature = 'velora:sync-event-images';

    /**
     * La description de la commande de console.
     *
     * @var string
     */
    protected $description = 'Copie le image_path des demandes d\'événements approuvées vers leurs événements liés si l\'événement n\'a pas d\'image.';

    /**
     * Exécute la commande de console.
     *
     * Parcourt les événements qui n'ont pas de chemin d'image mais dont la demande parente
     * en a un, et met à jour le modèle d'événement en conséquence.
     */
    public function handle(): int
    {
        $count = 0;

        // Trouve les événements auxquels il manque une image mais qui ont une demande avec une image
        Event::query()
            ->whereNull('image_path')
            ->whereHas('eventRequest', fn ($q) => $q->whereNotNull('image_path'))
            ->with('eventRequest')
            ->each(function (Event $event) use (&$count) {
                $eventRequest = $event->eventRequest;
                if (! $eventRequest instanceof EventRequest || $eventRequest->image_path === null) {
                    return;
                }

                // Met à jour le chemin d'image de l'événement à partir de sa demande
                $event->update(['image_path' => $eventRequest->image_path]);
                $count++;
            });

        $this->info("{$count} événement(s) mis à jour.");

        return self::SUCCESS;
    }
}

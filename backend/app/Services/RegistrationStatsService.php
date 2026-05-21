<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Registration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Service pour l'agrégation et la gestion des statistiques d'inscription.
 *
 * Ce service utilise le framework d'agrégation de MongoDB pour compter efficacement
 * les inscriptions sur plusieurs événements, avec la possibilité de filtrer par statut de paiement.
 * Il est principalement utilisé pour hydrater les listes d'événements avec des données de participation en temps réel.
 */
class RegistrationStatsService
{
    /**
     * Récupère les nombres d'inscriptions pour une liste d'identifiants d'événements.
     *
     * @param  iterable<mixed>  $eventIds  Liste des identifiants d'événements.
     * @param  string|null  $paymentStatus  Filtre optionnel (ex: 'paid', 'pending').
     * @return array<string, int> Carte de l'ID de l'événement par rapport au nombre d'inscriptions.
     */
    public function countsByEvent(iterable $eventIds, ?string $paymentStatus = null): array
    {
        // Normalise les IDs d'événements en un tableau propre de chaînes de caractères.
        $normalizedEventIds = [];
        foreach ($eventIds as $eventId) {
            if (! is_scalar($eventId)) {
                continue;
            }

            $eventId = (string) $eventId;
            if ($eventId === '') {
                continue;
            }

            $normalizedEventIds[$eventId] = true;
        }

        $eventIds = array_keys($normalizedEventIds);

        if ($eventIds === []) {
            return [];
        }

        // Construit les critères de correspondance MongoDB.
        $match = ['event_id' => ['$in' => $eventIds]];

        if ($paymentStatus !== null) {
            $match['payment_status'] = $paymentStatus;
        }

        /** @var MongoConnection $connection */
        $connection = DB::connection('mongodb');

        // Exécute l'agrégation MongoDB pour une performance élevée sur de grands ensembles de données.
        // Nous groupons par event_id et sommons les occurrences.
        $rows = $connection
            ->getDatabase()
            ->selectCollection((new Registration)->getTable())
            ->aggregate([
                ['$match' => $match],
                ['$group' => ['_id' => '$event_id', 'count' => ['$sum' => 1]]],
            ]);

        // Transforme le curseur MongoDB brut en un tableau associatif PHP.
        return collect(iterator_to_array($rows))
            ->mapWithKeys(fn (mixed $row): array => [
                $this->stringValue(data_get($row, '_id')) => $this->intValue(data_get($row, 'count', 0)),
            ])->filter(fn (int $count, string $eventId): bool => $eventId !== '')
            ->all();
    }

    /**
     * Attache les nombres d'inscriptions à une collection de modèles d'événements en tant qu'attribut dynamique.
     *
     * @param  Collection<int, Event>  $events  La collection de modèles Event à hydrater.
     * @param  string  $attribute  Le nom de l'attribut virtuel à définir (ex: 'paid_registrations_count').
     * @param  string|null  $paymentStatus  Filtre de statut optionnel pour les comptages.
     */
    public function attachCount(Collection $events, string $attribute, ?string $paymentStatus = null): void
    {
        // Récupère tous les comptages en une seule requête pour éviter les problèmes N+1.
        $counts = $this->countsByEvent($events->pluck('id'), $paymentStatus);

        // Injecte les comptages dans chaque instance d'événement.
        $events->each(function (Event $event) use ($attribute, $counts): void {
            $event->setAttribute($attribute, $counts[$this->stringValue($event->id)] ?? 0);
        });
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }
}

<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use MongoDB\Laravel\Connection as MongoConnection;
use RuntimeException;
use Throwable;

/**
 * Génère le rapport de santé de l'API publique utilisé par Docker et les vérifications manuelles.
 *
 * Ce point de terminaison vérifie les dépendances d'exécution réelles au lieu de renvoyer un "ok" statique.
 * Une réponse dégradée est toujours au format JSON afin que les outils locaux et les vérifications du frontend
 * puissent montrer quelle dépendance est en panne sans avoir à lire les logs du conteneur au préalable.
 */
class HealthCheckService
{
    /**
     * @return array{
     *     status: 'ok'|'degraded',
     *     checked_at: string,
     *     services: array<string, array{status: 'ok'|'down', error?: string}>
     * }
     */
    public function report(): array
    {
        $services = [
            'mongodb' => $this->checkMongo(),
        ];

        // Only check redis if it is actually used for something
        if (config('cache.default') === 'redis' || config('session.driver') === 'redis' || config('queue.default') === 'redis') {
            $services['redis'] = $this->checkRedis();
        }

        return [
            'status' => $this->allHealthy($services) ? 'ok' : 'degraded',
            'checked_at' => now()->toIso8601String(),
            'services' => $services,
        ];
    }

    /**
     * @param  array<string, array{status: 'ok'|'down', error?: string}>  $services
     */
    public function allHealthy(array $services): bool
    {
        foreach ($services as $service) {
            if ($service['status'] !== 'ok') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{status: 'ok'|'down', error?: string}
     */
    private function checkMongo(): array
    {
        try {
            $connection = DB::connection('mongodb');

            // Un mauvais pilote ici signifie que l'application ne fonctionne plus en mode MongoDB uniquement.
            if (! $connection instanceof MongoConnection) {
                throw new RuntimeException('La connexion MongoDB n\'utilise pas le pilote MongoDB.');
            }

            // ping est peu coûteux et vérifie que la connexion actuelle peut communiquer avec le serveur Mongo.
            $connection->getDatabase()->command(['ping' => 1])->toArray();

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return $this->down($exception);
        }
    }

    /**
     * @return array{status: 'ok'|'down', error?: string}
     */
    private function checkRedis(): array
    {
        try {
            // Redis gère le cache, les files d'attente, la limitation de débit et les sessions dans la pile locale.
            Redis::connection()->ping();

            return ['status' => 'ok'];
        } catch (Throwable $exception) {
            return $this->down($exception);
        }
    }

    /**
     * @return array{status: 'down', error: string}
     */
    private function down(Throwable $exception): array
    {
        // Inclure l'erreur de dépendance dans les réponses de santé en local/dev pour accélérer le dépannage.
        if (! (bool) config('app.debug')) {
            return [
                'status' => 'down',
                'error' => 'Dépendance indisponible.',
            ];
        }

        return [
            'status' => 'down',
            'error' => $exception->getMessage(),
        ];
    }
}

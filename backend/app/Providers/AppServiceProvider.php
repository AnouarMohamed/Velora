<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\EventRequest;
use App\Models\Feedback;
use App\Models\Payment;
use App\Models\PersonalAccessToken;
use App\Models\Registration;
use App\Models\User;
use App\Observers\AdminStatsCacheObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * AppServiceProvider
 *
 * Ce fournisseur est responsable de l'enregistrement et du démarrage des services de base de l'application.
 * Dans ce projet, il gère spécifiquement les surcharges de configuration pour MongoDB uniquement et
 * la personnalisation de Sanctum/Carbon.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Enregistre tous les services de l'application.
     *
     * Cette méthode surcharge les configurations de base de données et de pilotes au moment de l'exécution pour s'assurer
     * que MongoDB, Redis et les autres services requis sont utilisés quel que soit
     * la configuration initiale de l'environnement.
     */
    public function register(): void
    {
        config([
            'database.default' => 'mongodb',
            'database.connections' => [
                'mongodb' => config('database.connections.mongodb'),
            ],
            'cache.default' => config('cache.default', 'redis'),
            'queue.default' => config('queue.default', 'redis'),
            'session.driver' => config('session.driver', 'redis'),
        ]);
    }

    /**
     * Démarre tous les services de l'application.
     *
     * Cette méthode est appelée après l'enregistrement de tous les services. Elle configure :
     * - Sanctum pour utiliser un modèle PersonalAccessToken personnalisé (compatible MongoDB).
     * - Carbon pour utiliser un format spécifique de sérialisation de date pour les réponses JSON.
     */
    public function boot(): void
    {
        // Indique à Sanctum d'utiliser notre modèle de jeton compatible MongoDB
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->configureRateLimits();
        $this->configureModelObservers();

        // Standardise le format de date dans toute l'API
        Carbon::serializeUsing(fn (Carbon $date) => $date->format('Y-m-d H:i:s'));
    }

    private function configureModelObservers(): void
    {
        foreach ([Event::class, EventRequest::class, Feedback::class, Payment::class, Registration::class, User::class] as $model) {
            $model::observe(AdminStatsCacheObserver::class);
        }
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('auth.login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($this->loginThrottleKey($request))
            ->response(fn () => response()->json([
                'message' => 'Trop de tentatives de connexion. Réessayez dans une minute.',
            ], 429)));

        RateLimiter::for('auth.register', fn (Request $request): Limit => Limit::perMinute(3)
            ->by($request->ip())
            ->response(fn () => response()->json([
                'message' => 'Trop de créations de compte. Réessayez dans une minute.',
            ], 429)));
    }

    private function loginThrottleKey(Request $request): string
    {
        $email = $request->input('email');
        $email = is_scalar($email) ? Str::lower((string) $email) : '';

        return $email.'|'.$request->ip();
    }
}

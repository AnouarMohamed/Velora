<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * AppServiceProvider
 *
 * This provider is responsible for registering and bootstrapping core application services.
 * In this project, it specifically handles MongoDB-only configuration overrides and
 * Sanctum/Carbon customization.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method overrides database and driver configurations at runtime to ensure
     * that MongoDB, Redis, and other required services are used regardless of
     * the initial environment setup.
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
     * Bootstrap any application services.
     *
     * This method is called after all services are registered. It configures:
     * - Sanctum to use a custom PersonalAccessToken model (MongoDB-compatible).
     * - Carbon to use a specific date serialization format for JSON responses.
     */
    public function boot(): void
    {
        // Tell Sanctum to use our MongoDB-compatible token model
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Standardize date format across the API
        Carbon::serializeUsing(fn (Carbon $date) => $date->format('Y-m-d H:i:s'));
    }
}

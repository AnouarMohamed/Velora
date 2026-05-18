<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
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
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Carbon::serializeUsing(fn (Carbon $date) => $date->format('Y-m-d H:i:s'));
    }
}

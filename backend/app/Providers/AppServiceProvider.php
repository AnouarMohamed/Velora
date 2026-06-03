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

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Don't force redis if it's not configured
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->configureRateLimits();
        $this->configureModelObservers();

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

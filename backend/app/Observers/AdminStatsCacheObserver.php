<?php

namespace App\Observers;

use App\Services\Stats\AdminStatsService;
use Illuminate\Database\Eloquent\Model;

class AdminStatsCacheObserver
{
    public function saved(Model $model): void
    {
        AdminStatsService::forgetCache();
    }

    public function deleted(Model $model): void
    {
        AdminStatsService::forgetCache();
    }

    public function restored(Model $model): void
    {
        AdminStatsService::forgetCache();
    }

    public function forceDeleted(Model $model): void
    {
        AdminStatsService::forgetCache();
    }
}

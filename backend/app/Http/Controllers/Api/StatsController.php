<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Stats\AdminStatsService;
use App\Services\Stats\ClientStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for retrieving statistics for dashboards.
 *
 * Supports global stats for Admins and personal stats for Clients.
 */
class StatsController extends Controller
{
    /**
     * @param  AdminStatsService  $adminStats  Service for global admin-level statistics.
     * @param  ClientStatsService  $clientStats  Service for personal client-level statistics.
     */
    public function __construct(
        private readonly AdminStatsService $adminStats,
        private readonly ClientStatsService $clientStats,
    ) {}

    /**
     * Get global statistics (Admin view).
     *
     * Includes metrics like total users, events, and overall revenue.
     *
     * @return JsonResponse Global metrics.
     */
    public function admin(): JsonResponse
    {
        return response()->json($this->adminStats->payload());
    }

    /**
     * Get personal statistics for the authenticated user (Client view).
     *
     * Includes metrics like events attended and money spent.
     *
     * @return JsonResponse User-specific metrics.
     */
    public function client(Request $request): JsonResponse
    {
        return response()->json($this->clientStats->payloadFor($this->actor($request)));
    }

    /**
     * Retrieve and validate the authenticated user.
     */
    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}

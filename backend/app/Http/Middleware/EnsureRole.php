<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Middleware to restrict access based on user roles.
 *
 * This middleware checks if the authenticated user has one of the allowed roles
 * before permitting the request to proceed.
 */
class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * Parses the allowed roles (which can be passed as multiple arguments or a comma-separated string)
     * and validates the current user's role against this list.
     *
     * @param  string  ...$roles  Allowed roles (e.g. "admin", "organizer" or "admin,organizer")
     *
     * @throws HttpException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            // Require authentication
            abort(401);
        }

        // Normalize roles list from potential comma-separated strings or multiple arguments
        $allowed = [];
        foreach ($roles as $chunk) {
            $allowed = array_merge($allowed, array_map('trim', explode(',', $chunk)));
        }
        $allowed = array_values(array_unique(array_filter($allowed)));

        // Perform the role check
        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Accès refusé pour ce rôle.');
        }

        return $next($request);
    }
}

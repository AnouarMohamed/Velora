<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * @param  string  ...$roles  Segments depuis le pipeline (ex. « organizer » et « admin ») ou une seule chaîne « organizer,admin ».
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $allowed = [];
        foreach ($roles as $chunk) {
            $allowed = array_merge($allowed, array_map('trim', explode(',', $chunk)));
        }
        $allowed = array_values(array_unique(array_filter($allowed)));

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Accès refusé pour ce rôle.');
        }

        return $next($request);
    }
}

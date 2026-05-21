<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Middleware pour restreindre l'accès en fonction des rôles des utilisateurs.
 *
 * Ce middleware vérifie si l'utilisateur authentifié possède l'un des rôles autorisés
 * avant de permettre à la requête de se poursuivre.
 */
class EnsureRole
{
    /**
     * @var array<string, string>
     */
    private const ROLE_ALIASES = [
        'organizer' => User::ROLE_ORGANIZER,
    ];

    /**
     * Gérer une requête entrante.
     *
     * Analyse les rôles autorisés (qui peuvent être passés sous forme de plusieurs arguments ou d'une chaîne séparée par des virgules)
     * et valide le rôle de l'utilisateur actuel par rapport à cette liste.
     *
     * @param  string  ...$roles  Rôles autorisés (ex: "admin", "organizer" ou "admin,organizer")
     *
     * @throws HttpException
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) {
            // Authentification requise
            abort(401);
        }

        // Normaliser la liste des rôles à partir de chaînes potentielles séparées par des virgules ou de plusieurs arguments
        $allowed = [];
        foreach ($roles as $chunk) {
            foreach (explode(',', $chunk) as $role) {
                $role = trim($role);

                if ($role !== '') {
                    $allowed[] = $this->normalizeRole($role);
                }
            }
        }
        $allowed = array_values(array_unique(array_filter($allowed)));

        // Effectuer la vérification du rôle
        if (! in_array($this->normalizeRole($user->role), $allowed, true)) {
            abort(403, 'Accès refusé pour ce rôle.');
        }

        $response = $next($request);
        abort_unless($response instanceof Response, 500);

        return $response;
    }

    private function normalizeRole(string $role): string
    {
        return self::ROLE_ALIASES[$role] ?? $role;
    }
}

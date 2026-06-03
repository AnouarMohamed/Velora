<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyApiSecurityHeaders
{
    /** @var array<string, string> */
    private const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    public function handle(Request , Closure ): Response
    {
        $response = $next($request);

        if ($response instanceof Response) {
            return self::apply($response);
        }

        return $response;
    }

    public static function apply(Response $response): Response
    {
        foreach (self::HEADERS as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}

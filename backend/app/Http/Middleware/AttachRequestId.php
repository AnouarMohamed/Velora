<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AttachRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = self::resolve($request);
        $request->attributes->set('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        abort_unless($response instanceof Response, 500);

        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    public static function resolve(Request $request): string
    {
        $header = $request->headers->get(self::HEADER);

        if (is_string($header) && self::isValid($header)) {
            return $header;
        }

        return (string) Str::uuid();
    }

    private static function isValid(string $requestId): bool
    {
        return $requestId !== ''
            && strlen($requestId) <= 128
            && preg_match('/^[A-Za-z0-9._:-]+$/', $requestId) === 1;
    }
}

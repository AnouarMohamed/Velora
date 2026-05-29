<?php

use App\Exceptions\Contracts\ApiException;
use App\Http\Middleware\ApplyApiSecurityHeaders;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->api(append: [
            AttachRequestId::class,
            ApplyApiSecurityHeaders::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $_exception): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (ApiException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json($exception->toResponsePayload(), $exception->statusCode());
        });

        $exceptions->respond(function (Response $response, Throwable $_exception, Request $request): Response {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return $response;
            }

            $requestId = $request->attributes->get('request_id');
            if (! is_string($requestId) || $requestId === '') {
                $requestId = AttachRequestId::resolve($request);
            }

            $response->headers->set(AttachRequestId::HEADER, $requestId);

            return ApplyApiSecurityHeaders::apply($response);
        });
    })->create();

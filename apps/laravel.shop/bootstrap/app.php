<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Core middleware (aliases, web group appends, CSRF exceptions) is
        // wired by Nreljed\Platform\PlatformServiceProvider.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->renderable(function (HttpException $e, Request $request) {
            if ($request->inertia()) {
                $retryAfter = (int) ($e->getHeaders()['Retry-After'] ?? 0);

                return Inertia::render('Error', [
                    'status' => $e->getStatusCode(),
                    'retryAfter' => $retryAfter > 0 ? $retryAfter : null,
                ])
                    ->toResponse($request)
                    ->setStatusCode($e->getStatusCode());
            }
        });
    })->create();

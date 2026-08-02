<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\DashboardCore\Http\Middleware\AdminMiddleware;
use Nreljed\DashboardCore\Http\Middleware\CheckIpAccess;
use Nreljed\DashboardCore\Http\Middleware\CheckMaintenanceMode;
use Nreljed\DashboardCore\Http\Middleware\CheckPermission;
use Nreljed\DashboardCore\Http\Middleware\CheckRole;
use Nreljed\DashboardCore\Http\Middleware\ConfigureSessionLifetime;
use Nreljed\DashboardCore\Http\Middleware\ContentSecurityPolicy;
use Nreljed\DashboardCore\Http\Middleware\HandleInertiaRequests;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            ConfigureSessionLifetime::class,
            CheckIpAccess::class,
            ContentSecurityPolicy::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'dashboard/account/avatar',
            'deploy/*',
            'api/notifications/unread-count',
            'api/notifications/recent',
            'api/notifications/',
            'csp-report',
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'maintenance' => CheckMaintenanceMode::class,
            'session-lifetime' => ConfigureSessionLifetime::class,
            'ip-access' => CheckIpAccess::class,
        ]);
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

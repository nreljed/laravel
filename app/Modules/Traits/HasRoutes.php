<?php

namespace App\Modules\Traits;

use Illuminate\Support\Facades\Route;

trait HasRoutes
{
    protected function loadRoutes(string $routesPath, string $prefix, ?string $name = null, ?string $permission = null): void
    {
        $attributes = [
            'middleware' => ['web', 'auth', 'verified'],
            'prefix' => $prefix,
        ];

        if ($name) {
            $attributes['as'] = $name;
        }

        if ($permission) {
            $attributes['middleware'][] = 'permission:'.$permission;
        }

        Route::group($attributes, function () use ($routesPath) {
            require $routesPath;
        });
    }

    protected function loadAdminRoutes(string $routesPath, string $prefix, ?string $name = null, ?string $permission = null): void
    {
        $attributes = [
            'middleware' => ['web', 'auth', 'verified', 'admin'],
            'prefix' => $prefix,
        ];

        if ($name) {
            $attributes['as'] = $name;
        }

        if ($permission) {
            $attributes['middleware'][] = 'permission:'.$permission;
        }

        Route::group($attributes, function () use ($routesPath) {
            require $routesPath;
        });
    }

    protected function loadPublicRoutes(string $routesPath, string $prefix, ?string $name = null): void
    {
        $attributes = [
            'middleware' => ['web'],
            'prefix' => $prefix,
        ];

        if ($name) {
            $attributes['as'] = $name;
        } else {
            $computed = trim(str_replace('/', '.', $prefix), '.');
            if ($computed !== '') {
                $attributes['as'] = $computed.'.';
            }
        }

        Route::group($attributes, function () use ($routesPath) {
            require $routesPath;
        });
    }
}

<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Nreljed\MarketingModule\MarketingServiceProvider;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

final class PublicRouteTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
    }

    public function test_the_redirect_route_uses_the_configured_prefix(): void
    {
        $this->container['config']->set('marketing.links.prefix', 'go');

        $router = new Router($this->container['events'], $this->container);
        $this->container->instance('router', $router);
        Route::clearResolvedInstances();

        $provider = new class($this->container) extends MarketingServiceProvider
        {
            public function loadPublicRoutes(): void
            {
                $this->loadRoutes();
            }
        };

        $provider->loadPublicRoutes();

        $route = collect($router->getRoutes()->getRoutes())->first(
            fn ($registered): bool => $registered->getName() === 'marketing.links.redirect',
        );

        $this->assertNotNull($route);
        $this->assertSame('go/{code}', $route->uri());
        $this->assertContains('throttle:marketing-links', $route->gatherMiddleware());
    }
}

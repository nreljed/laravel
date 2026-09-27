<?php

namespace Nreljed\ShopModule\Tests;

use Nreljed\ShopModule\Http\Controllers\AttributesController;
use Nreljed\ShopModule\Http\Controllers\CategoriesController;
use Nreljed\ShopModule\Http\Controllers\OrdersController;
use Nreljed\ShopModule\Http\Controllers\ProductsController;
use Nreljed\ShopModule\Http\Controllers\ProductSeoController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Two ways a staff screen can look fine in the route list and be broken or
 * over-broad in the browser.
 *
 * **Shadowed literals.** Laravel matches routes in declaration order, so a
 * literal segment registered *after* a wildcard is dead code. `GET
 * /dashboard/shop/orders/export` and `GET /dashboard/shop/customers/export`
 * were both declared below `/orders/{order}` and `/customers/{user}`, so the
 * Export buttons 404'd: the request resolved as an order lookup for the string
 * "export". `route:list` still showed the route with its controller, which is
 * why it survived - the tooling reports registration, not reachability.
 *
 * **Actions that inherit the group gate.** The `/dashboard/shop` group is
 * gated on `shop.manage`, and almost every action re-checks a policy anyway,
 * so the few that did not stood out only by comparison. The taxonomy reads,
 * the image upload and the SEO generation each wrote something (a Media row, a
 * product's meta, or a call on the caller's own AI key) without a check of
 * their own, so a permission split that narrows `shop.manage` would leave them
 * reachable. They are asserted here so the pattern stays uniform.
 */
final class StaffRouteOrderAndAuthorizationTest extends TestCase
{
    // ---------------------------------------------------------------------
    // Literal segments must precede their wildcard sibling.
    // ---------------------------------------------------------------------

    public function test_the_order_export_is_not_shadowed_by_the_order_wildcard(): void
    {
        $this->assertLiteralDeclaredBefore(
            'orders.export',
            'orders.show',
            'The orders Export button 404s while `/orders/export` resolves as an order lookup for the string "export".'
        );
    }

    public function test_the_customer_export_is_not_shadowed_by_the_user_wildcard(): void
    {
        $this->assertLiteralDeclaredBefore(
            'customers.export',
            'customers.show',
            'The customers Export button 404s while `/customers/export` resolves as a user lookup for the string "export".'
        );
    }

    /**
     * The order-create autocompletes sit at `/orders/search/products` and
     * `/orders/search/customers` - three segments, below `/orders/{order}` in
     * the file. They work, because `/orders/{order}/edit` only swallows paths
     * whose last segment is literally `edit`. Asserted explicitly so a future
     * wildcard of the same shape is not introduced above them by accident.
     */
    public function test_the_order_search_endpoints_are_still_reachable(): void
    {
        $this->assertNotSwallowedByAnEarlierRoute('orders.search-products');
        $this->assertNotSwallowedByAnEarlierRoute('orders.search-customers');
    }

    /**
     * The general rule, so the next literal someone adds under a wildcard is
     * caught here instead of by a customer clicking Export.
     */
    public function test_no_literal_segment_is_declared_after_a_wildcard_that_would_swallow_it(): void
    {
        $routes = $this->routes();
        $shadowed = [];

        foreach ($routes as $name => $route) {
            foreach ($routes as $otherName => $other) {
                if ($otherName === $name || $other['line'] >= $route['line']) {
                    continue;
                }

                if ($this->wouldSwallow($other, $route)) {
                    $shadowed[] = sprintf(
                        '%s (%s, line %d) is swallowed by %s (%s, line %d)',
                        $name,
                        $route['uri'],
                        $route['line'],
                        $otherName,
                        $other['uri'],
                        $other['line']
                    );
                }
            }
        }

        $this->assertSame([], $shadowed, "Literal routes declared after a wildcard that matches them are dead:\n".implode("\n", $shadowed));
    }

    // ---------------------------------------------------------------------
    // Every staff action carries its own check.
    // ---------------------------------------------------------------------

    public function test_the_category_and_attribute_reads_authorise_themselves(): void
    {
        // These read the taxonomy rather than a product, so they are the two
        // screens a narrowed `shop.manage` would most obviously still expose:
        // a category list and a raw JSON term list.
        $this->assertActionAuthorises(CategoriesController::class, 'index');
        $this->assertActionAuthorises(AttributesController::class, 'index');
        $this->assertActionAuthorises(AttributesController::class, 'listTerms');
    }

    public function test_the_image_upload_authorises_itself(): void
    {
        $this->assertActionAuthorises(ProductsController::class, 'uploadImage');
    }

    public function test_the_image_delete_keeps_authorising_itself(): void
    {
        // It resolves a product from `entity_id` first, so the gate is on that
        // model rather than the class.
        $this->assertActionAuthorises(ProductsController::class, 'deleteImage');
    }

    public function test_the_seo_generation_authorises_itself(): void
    {
        // It spends the caller's own AI key and writes the product's meta.
        $this->assertActionAuthorises(ProductSeoController::class, 'generate');
    }

    // ---------------------------------------------------------------------
    // Helpers.
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{uri: string, verb: string, line: int}>
     */
    private function routes(): array
    {
        // `src/Http/Controllers` -> `src/Routes/web.php`.
        $controllerDir = dirname((string) (new ReflectionClass(OrdersController::class))->getFileName());
        $routesFile = dirname($controllerDir, 2).DIRECTORY_SEPARATOR.'Routes'.DIRECTORY_SEPARATOR.'web.php';

        if (! is_file($routesFile)) {
            $this->fail("Staff route file not found at {$routesFile}.");
        }

        $lines = file($routesFile, FILE_IGNORE_NEW_LINES);

        $routes = [];

        foreach ($lines as $index => $line) {
            if (! preg_match("#Route::(get|post|put|patch|delete)\('([^']+)'.*->name\('([^']+)'\)#", $line, $m)) {
                continue;
            }

            $routes[$m[3]] = ['uri' => $m[2], 'verb' => strtoupper($m[1]), 'line' => $index + 1];
        }

        return $routes;
    }

    /**
     * True when `$wildcard`'s URI would match the request path `$literal` on the
     * same verb: same segment count, identical everywhere except where the
     * wildcard absorbs a literal segment.
     *
     * The verb matters. `PUT /attributes/{attribute}/terms/{term}` sits above
     * `POST /attributes/{attribute}/terms/reorder` and looks like a swallow,
     * but a POST never reaches a PUT route, so both work. Comparing URIs alone
     * reported a dozen of those as dead.
     */
    private function wouldSwallow(array $wildcard, array $literal): bool
    {
        if ($wildcard['verb'] !== $literal['verb']) {
            return false;
        }

        $wildcardSegments = explode('/', trim($wildcard['uri'], '/'));
        $literalSegments = explode('/', trim($literal['uri'], '/'));

        if (count($wildcardSegments) !== count($literalSegments)) {
            return false;
        }

        $hasParameter = false;
        $hasLiteral = false;

        foreach ($wildcardSegments as $i => $segment) {
            $isParameter = str_starts_with($segment, '{');

            if ($isParameter) {
                $hasParameter = true;

                if (! $hasLiteral && ! str_starts_with($literalSegments[$i], '{')) {
                    $hasLiteral = true;
                }

                continue;
            }

            if ($segment !== $literalSegments[$i]) {
                return false;
            }
        }

        return $hasParameter && $hasLiteral;
    }

    private function assertLiteralDeclaredBefore(string $literal, string $wildcard, string $why): void
    {
        $routes = $this->routes();

        $this->assertArrayHasKey($literal, $routes);
        $this->assertArrayHasKey($wildcard, $routes);

        $this->assertLessThan(
            $routes[$wildcard]['line'],
            $routes[$literal]['line'],
            $why
        );
    }

    private function assertNotSwallowedByAnEarlierRoute(string $name): void
    {
        $routes = $this->routes();

        $this->assertArrayHasKey($name, $routes);

        $blockers = [];

        foreach ($routes as $otherName => $other) {
            if ($otherName === $name || $other['line'] >= $routes[$name]['line']) {
                continue;
            }

            if ($this->wouldSwallow($other, $routes[$name])) {
                $blockers[] = $otherName.' ('.$other['uri'].')';
            }
        }

        $this->assertSame(
            [],
            $blockers,
            "{$name} is unreachable: ".implode(', ', $blockers).' is declared above it and matches its path.'
        );
    }

    private function assertActionAuthorises(string $controller, string $action): void
    {
        $reflection = new ReflectionMethod($controller, $action);
        $file = (string) $reflection->getFileName();
        $source = (string) file_get_contents($file);

        $start = strpos($source, "function {$action}(");
        $this->assertIsInt($start, "{$action}() not found in $controller.");

        $next = strpos($source, "\n    public function ", $start + 1);
        $body = $next === false ? substr($source, $start) : substr($source, $start, $next - $start);

        $this->assertStringContainsString(
            '$this->authorize(',
            $body,
            "{$controller}::{$action}() has no check of its own and rides the route group's shop.manage gate alone."
        );
    }
}

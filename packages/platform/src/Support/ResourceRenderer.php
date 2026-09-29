<?php

namespace Nreljed\Platform\Support;

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Nreljed\Platform\Services\FeatureRegistry;

/**
 * Renders the generic kernel admin pages for a declared resource.
 *
 * The contracting idea behind §5.2: a feature declares a ResourceDefinition
 * (schema + route names) and its own controller stays authoritative for data
 * — the renderer only frames that data with the kernel's index/form pages.
 *
 * Every route name the descriptor declares MUST resolve to a registered
 * route at render time. A descriptor that names a route the feature has not
 * wired yet is a contract violation, not a runtime guess: we fail loudly so
 * the missing route is never silently rendered as a dead link.
 *
 * Edit/update/destroy routes must bind their record on the literal parameter
 * name "record" (ResourceRenderer::RECORD_PARAM), which is what the generic
 * pages resolve client-side via Ziggy.
 */
class ResourceRenderer
{
    /** Literal route parameter the generic pages bind for record-scoped URLs. */
    public const RECORD_PARAM = 'record';

    public function __construct(protected FeatureRegistry $registry) {}

    /**
     * Render the generic index page for a resource.
     *
     * @param  array<string, mixed>  $data  Rows (and any page-level metadata)
     *                                      the provider's controller resolved.
     * @param  array<string, mixed>  $extra  Additional props forwarded verbatim.
     */
    public function renderIndex(string $key, array $data, array $extra = []): Response
    {
        $definition = $this->resolve($key);

        return Inertia::render('dashboard/admin/resources/index', array_merge([
            'resource' => $definition->toArray(),
            'data' => $data,
            'resourceKey' => $key,
        ], $extra));
    }

    /**
     * Render the generic form page for a resource.
     *
     * @param  array<string, mixed>|null  $record  Existing row when editing.
     * @param  non-empty-string  $method  HTTP verb the form submits with.
     * @param  non-empty-string  $action  URL the form submits to.
     * @param  array<string, mixed>  $extra  Additional props forwarded verbatim.
     */
    public function renderForm(string $key, ?array $record, string $method, string $action, array $extra = []): Response
    {
        $definition = $this->resolve($key);

        return Inertia::render('dashboard/admin/resources/form', array_merge([
            'resource' => $definition->toArray(),
            'record' => $record,
            'formMethod' => strtoupper($method),
            'formAction' => $action,
            'resourceKey' => $key,
        ], $extra));
    }

    /**
     * Resolve a validated descriptor and enforce its route-name contract.
     */
    protected function resolve(string $key): ResourceDefinition
    {
        $definition = $this->registry->getAdminResource($key);

        if ($definition === null) {
            throw new InvalidArgumentException(
                "No admin resource implements key [{$key}]; declare it via a feature's getAdminResources()."
            );
        }

        $missing = array_filter(
            $definition->getRoutes(),
            fn ($name): bool => ! is_string($name) || $name === '' || ! Route::has($name),
        );

        if ($missing !== []) {
            throw new InvalidArgumentException(
                'Resource ['.$key.'] declares route name(s) that are not registered: '
                .implode(', ', array_keys($missing))
                .'. Register the routes behind the owning feature (record param must be named "'
                .self::RECORD_PARAM.'" for edit/update/destroy).'
            );
        }

        return $definition;
    }
}

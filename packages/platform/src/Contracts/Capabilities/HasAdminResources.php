<?php

namespace Nreljed\Platform\Contracts\Capabilities;

use Nreljed\Platform\Support\ResourceDefinition;

/**
 * The feature exposes declarative admin resources (index/create/edit screens
 * driven by the shared resource kernel).
 */
interface HasAdminResources
{
    /**
     * The resources this feature exposes, e.g. its TaxRates, Currencies and
     * Products. Each entry is a `Nreljed\Platform\Support\ResourceDefinition`
     * carrying the table schema, routes and permissions; the runtime lives in
     * the frontend (`resources/js/lib/resource-schema.ts`), so this method
     * returns descriptors, not components.
     *
     * Keys must be globally unique across enabled features and the descriptors
     * must validate against the shipped schema — the registry fails loudly
     * otherwise. Scaffold one with `php artisan make:resource`.
     *
     * @return array<int, ResourceDefinition>
     */
    public function getAdminResources(): array;
}

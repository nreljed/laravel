<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature contributes shared Inertia props on every page.
 *
 * Use sparingly: this payload is serialized into every response. Prefer a
 * page-specific prop unless every page genuinely needs the value (e.g. a
 * storefront cart count).
 */
interface HasSharedData
{
    /**
     * @return array<string, mixed>
     */
    public function getSharedData(): array;
}

<?php

namespace Nreljed\Platform\Contracts\Capabilities;

use Nreljed\Platform\Support\ContentDescription;

/**
 * The feature exposes a content type, so the shared CRUD/editor/taxonomy
 * pipeline can serve it without a bespoke implementation.
 */
interface HasContentType
{
    /**
     * A declarative content-type description, or null when the feature does not
     * expose a content type. The ContentDescription value object captures the
     * model class, label, feature flags, taxonomy declarations, SEO meta config
     * and media settings, so one generic pipeline can serve CRUD, editor,
     * upload, taxonomy and notification wiring.
     */
    public function getContentDescription(): ?ContentDescription;
}

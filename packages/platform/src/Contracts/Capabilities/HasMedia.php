<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature uploads media and participates in the shared media library.
 */
interface HasMedia
{
    /**
     * The storage subdirectory this feature uses for media uploads, e.g.
     * "posts" or "products". Used by the shared media library to scope queries
     * and by the orphan scanner to reconcile disk contents.
     *
     * Return null when the feature does not upload media.
     */
    public function getMediaSubdirectory(): ?string;

    /**
     * A map of storage folder => model class that the `core:cleanup-orphan-media`
     * command should scan for this feature, e.g.
     * `['products' => Product::class]`. Aggregated alongside the host
     * `media.orphan_media` config.
     *
     * @return array<string, class-string>
     */
    public function getOrphanMediaTargets(): array;

    /**
     * Gallery configuration for the shared uploader (accepted types, size
     * limits, aspect ratios).
     *
     * @return array<string, mixed>
     */
    public function getGalleryConfig(): array;
}

<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Database\Eloquent\Model;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\UrlAlias;

/**
 * Records and resolves storefront URL aliases (PR 11A).
 *
 * Aliases exist so a renamed product or category keeps answering at its old
 * URL, as a 301 to the new canonical one. The storefront serves
 * `/store/{slug}` and `/store/category/{slug}`, so those are the only two path
 * shapes this service knows - everything else is a different feature.
 */
class UrlAliasService
{
    /** The storefront path prefix a target's route lives under. */
    public function basePath(Model $target): string
    {
        return $target instanceof ProductCategory ? '/store/category' : '/store';
    }

    /** The canonical storefront path for a target with its current slug. */
    public function pathFor(Model $target): string
    {
        return $this->basePath($target).'/'.$target->slug;
    }

    /**
     * The storefront path a target with the given *legacy* slug would have
     * answered at - the path the alias has to cover.
     */
    public function pathForSlug(Model $target, string $legacySlug): string
    {
        return $this->basePath($target).'/'.$legacySlug;
    }

    /**
     * Remember that a legacy path now belongs to this target.
     *
     * Returns null when the path is already the target's canonical one - an
     * alias that points at itself would redirect 301 straight back to the
     * requesting URL, which is a loop, not a redirect.
     */
    public function remember(string $legacyPath, Model $target): ?UrlAlias
    {
        if ($legacyPath === $this->pathFor($target)) {
            return null;
        }

        return UrlAlias::updateOrCreate(
            ['path' => $legacyPath],
            ['target_type' => $target->getMorphClass(), 'target_id' => $target->getKey()],
        );
    }

    /** The target a legacy path used to belong to, or null when it never did. */
    public function resolve(string $path): ?Model
    {
        return UrlAlias::where('path', $path)->first()?->target;
    }

    /** Drop every alias that points at a target, e.g. when it is deleted. */
    public function forgetTarget(Model $target): int
    {
        return UrlAlias::where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())
            ->delete();
    }
}
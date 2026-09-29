<?php

namespace Nreljed\ShopModule\Observers;

use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Services\UrlAliasService;

/**
 * Keeps `url_aliases` in step with product and category slugs (PR 11A).
 *
 * A slug is the storefront URL, so renaming one orphans every link that used
 * the old form. Recording the old path as an alias turns those links into 301s
 * instead of 404s. Hooking the model events covers every write path the same
 * way `SyncsPriceLedger` does for prices - the form, CSV import, bulk actions,
 * anything - rather than trusting each controller to remember.
 */
class SyncsUrlAliases
{
    public function __construct(
        protected UrlAliasService $aliasService,
    ) {}

    public function updated(Product|ProductCategory $target): void
    {
        if (! $target->wasChanged('slug')) {
            return;
        }

        $this->aliasService->remember(
            $this->aliasService->pathForSlug($target, (string) $target->getOriginal('slug')),
            $target,
        );
    }

    /**
     * A deleted target's aliases must go with it: the 301 resolver would
     * otherwise route the old path to a model a fresh route-model binding can
     * no longer find, which trades one 404 for another.
     */
    public function deleted(Product|ProductCategory $target): void
    {
        $this->aliasService->forgetTarget($target);
    }
}
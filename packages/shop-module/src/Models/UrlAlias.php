<?php

namespace Nreljed\ShopModule\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A storefront URL that used to belong to a product or category.
 *
 * When a slug changes, the old path is kept here so the storefront can answer
 * it with a 301 to the new canonical URL instead of a 404 (PR 11A). Rows are
 * written by the `SyncsUrlAliases` observer and read by the
 * `RedirectLegacyUrlAliases` middleware; there is no admin surface for them.
 */
class UrlAlias extends Model
{
    protected $fillable = ['path', 'target_type', 'target_id'];

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
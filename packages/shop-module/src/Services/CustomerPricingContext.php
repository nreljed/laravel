<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Nreljed\ShopModule\Models\CustomerGroup;

/**
 * Resolves the customer group whose pricing applies to the current shopper
 * (PR 10C).
 *
 * Group overrides only apply to a group whose `allows_business_pricing` flag
 * is set, so a retail-only group can carry a group row without it leaking into
 * public pricing. The lookup is request-scoped and memoized because the
 * storefront resolves prices for many products per visit.
 */
class CustomerPricingContext
{
    protected ?int $resolvedGroupId = null;

    protected bool $resolved = false;

    /**
     * The group id to resolve prices for, or null for public pricing.
     */
    public function groupId(?Authenticatable $user = null): ?int
    {
        $user ??= auth()->user();

        if (! $user) {
            return null;
        }

        if (! $this->resolved) {
            $this->resolvedGroupId = $this->lookup($user);
            $this->resolved = true;
        }

        return $this->resolvedGroupId;
    }

    /**
     * Groups that can hold a price override — the options offered in the
     * product form.
     *
     * @return Collection<int, CustomerGroup>
     */
    public function priceableGroups(): Collection
    {
        if (! $this->tablesExist()) {
            return new Collection;
        }

        return CustomerGroup::query()
            ->where('allows_business_pricing', true)
            ->orderBy('label')
            ->get();
    }

    protected function lookup(Authenticatable $user): ?int
    {
        if (! $this->tablesExist()) {
            return null;
        }

        return CustomerGroup::query()
            ->where('allows_business_pricing', true)
            ->whereHas('customers', fn ($query) => $query->whereHas('users', fn ($users) => $users->where('users.id', $user->getAuthIdentifier())))
            ->value('id');
    }

    protected function tablesExist(): bool
    {
        return Schema::hasTable('customer_user') && Schema::hasTable('customer_groups');
    }
}

<?php

namespace Nreljed\MarketingModule\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\Segment;

class SegmentEvaluator
{
    public function query(Segment $segment): Builder
    {
        $conditions = $segment->conditions ?? [];

        if (empty($conditions)) {
            return Contact::query()->whereRaw('1 = 0');
        }

        return Contact::query()->where(function (Builder $query) use ($conditions) {
            foreach ($conditions as $group) {
                if (empty($group)) {
                    continue;
                }

                $query->orWhere(function (Builder $groupQuery) use ($group) {
                    foreach ($group as $condition) {
                        if (empty($condition['field']) || ! isset($condition['operator'])) {
                            continue;
                        }

                        $this->applyCondition($groupQuery, $condition);
                    }
                });
            }
        });
    }

    public function count(Segment $segment): int
    {
        return $this->query($segment)->count();
    }

    public function contacts(Segment $segment)
    {
        return $this->query($segment)->get();
    }

    protected function applyCondition(Builder $query, array $condition): void
    {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'] ?? null;

        if ($field === 'has_purchased_product') {
            $this->applyProductPurchaseCondition($query, $operator, $value);

            return;
        }

        if ($field === 'tags') {
            $this->applyTagCondition($query, $operator, $value);

            return;
        }

        if ($field === 'has_user_account') {
            $query->whereNotNull('user_id');

            return;
        }

        match ($operator) {
            'equals' => $query->where($field, '=', $value),
            'not_equals' => $query->where($field, '!=', $value),
            'contains' => $query->where($field, 'like', "%{$value}%"),
            'not_contains' => $query->where($field, 'not like', "%{$value}%"),
            'starts_with' => $query->where($field, 'like', "{$value}%"),
            'ends_with' => $query->where($field, 'like', "%{$value}"),
            'greater_than' => $query->where($field, '>', $value),
            'greater_or_equal' => $query->where($field, '>=', $value),
            'less_than' => $query->where($field, '<', $value),
            'less_or_equal' => $query->where($field, '<=', $value),
            'is_empty' => $query->where(function ($q) use ($field) {
                $q->whereNull($field)->orWhere($field, '');
            }),
            'is_not_empty' => $query->where(function ($q) use ($field) {
                $q->whereNotNull($field)->where($field, '!=', '');
            }),
            'in' => $query->whereIn($field, is_array($value) ? $value : [$value]),
            'not_in' => $query->whereNotIn($field, is_array($value) ? $value : [$value]),
            default => null,
        };
    }

    protected function applyProductPurchaseCondition(Builder $query, string $operator, $value): void
    {
        if (! $value) {
            return;
        }

        $productId = (int) $value;

        if (! Schema::hasTable('orders') || ! Schema::hasTable('order_items')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($subQuery) use ($productId) {
            $subQuery->select(DB::raw(1))
                ->from('orders')
                ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                ->whereColumn('orders.customer_email', 'contacts.email')
                ->where('order_items.product_id', $productId);
        });
    }

    protected function applyTagCondition(Builder $query, string $operator, $value): void
    {
        if (! $value) {
            return;
        }

        $tagIds = is_array($value) ? $value : [$value];

        match ($operator) {
            'has_any' => $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            }),
            'has_all' => $query->where(function ($q) use ($tagIds) {
                foreach ($tagIds as $tagId) {
                    $q->whereHas('tags', function ($subQ) use ($tagId) {
                        $subQ->where('tags.id', $tagId);
                    });
                }
            }),
            'has_none' => $query->whereDoesntHave('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            }),
            default => null,
        };
    }
}

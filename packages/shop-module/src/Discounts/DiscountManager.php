<?php

namespace Nreljed\ShopModule\Discounts;

use Nreljed\ShopModule\Discounts\Types\BuyXGetYDiscount;
use Nreljed\ShopModule\Discounts\Types\FixedDiscount;
use Nreljed\ShopModule\Discounts\Types\FixedProductDiscount;
use Nreljed\ShopModule\Discounts\Types\FreeShippingDiscount;
use Nreljed\ShopModule\Discounts\Types\PercentageDiscount;
use Nreljed\ShopModule\Discounts\Types\PercentageProductDiscount;
use Nreljed\ShopModule\Discounts\Types\TieredDiscount;

class DiscountManager
{
    /** @var array<string, DiscountType> */
    protected array $types = [];

    /**
     * @param  array<int, DiscountType>  $types
     */
    public function __construct(array $types = [])
    {
        foreach ($types as $type) {
            $this->register($type);
        }
    }

    public static function defaults(): DiscountManager
    {
        return new self([
            new PercentageDiscount,
            new FixedDiscount,
            new FreeShippingDiscount,
            new BuyXGetYDiscount,
            new FixedProductDiscount,
            new PercentageProductDiscount,
            new TieredDiscount,
        ]);
    }

    public function register(DiscountType $type): void
    {
        $this->types[$type->type()] = $type;
    }

    public function driver(string $type): ?DiscountType
    {
        return $this->types[$type] ?? null;
    }

    /** @return array<string, DiscountType> */
    public function types(): array
    {
        return $this->types;
    }
}

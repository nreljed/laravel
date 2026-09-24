<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class Packed extends OrderStatusType
{
    public function code(): string
    {
        return 'packed';
    }

    public function label(): string
    {
        return 'Packed';
    }

    protected function allowedTransitions(): array
    {
        return ['shipped', 'cancelled'];
    }
}

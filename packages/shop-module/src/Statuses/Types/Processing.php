<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class Processing extends OrderStatusType
{
    public function code(): string
    {
        return 'processing';
    }

    public function label(): string
    {
        return 'Processing';
    }

    protected function allowedTransitions(): array
    {
        return ['packed', 'cancelled'];
    }
}

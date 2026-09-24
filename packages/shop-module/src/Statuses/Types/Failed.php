<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class Failed extends OrderStatusType
{
    public function code(): string
    {
        return 'failed';
    }

    public function label(): string
    {
        return 'Failed';
    }

    protected function allowedTransitions(): array
    {
        return ['pending_payment', 'cancelled'];
    }
}

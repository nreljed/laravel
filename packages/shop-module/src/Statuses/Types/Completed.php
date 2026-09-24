<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses\Types;

use Nreljed\ShopModule\Statuses\OrderStatusType;

final class Completed extends OrderStatusType
{
    public function code(): string
    {
        return 'completed';
    }

    public function label(): string
    {
        return 'Completed';
    }

    protected function allowedTransitions(): array
    {
        return [];
    }
}

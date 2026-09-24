<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Statuses;

use Closure;
use InvalidArgumentException;

final class OrderNumberGenerator
{
    /**
     * @param  Closure(): int  $nextSequence
     */
    public function __construct(
        private readonly string $prefix,
        private readonly Closure $nextSequence,
        private readonly int $padding = 6,
    ) {
        if ($prefix === '') {
            throw new InvalidArgumentException('Order number prefix must not be empty.');
        }

        if ($padding < 1) {
            throw new InvalidArgumentException('Order number padding must be >= 1.');
        }
    }

    public function generate(?\DateTimeInterface $at = null): string
    {
        $at ??= new \DateTimeImmutable;

        $sequence = ($this->nextSequence)();

        return sprintf('%s-%s-%0'.$this->padding.'d', $this->prefix, $at->format('Y'), $sequence);
    }
}

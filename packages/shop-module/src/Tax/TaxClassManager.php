<?php

declare(strict_types=1);

namespace Nreljed\ShopModule\Tax;

use Nreljed\ShopModule\Tax\Types\Reduced;
use Nreljed\ShopModule\Tax\Types\Standard;
use Nreljed\ShopModule\Tax\Types\Zero;

final class TaxClassManager
{
    /** @var array<string, TaxClassContract> */
    private array $classes = [];

    /**
     * @param  array<int, TaxClassContract>  $classes
     */
    public function __construct(array $classes = [])
    {
        foreach ($classes as $class) {
            $this->register($class);
        }
    }

    public static function defaults(): self
    {
        return new self([new Standard(), new Reduced(), new Zero()]);
    }

    public function register(TaxClassContract $class): void
    {
        $this->classes[$class->code()] = $class;
    }

    public function class(string $code): ?TaxClassContract
    {
        return $this->classes[$code] ?? null;
    }

    /**
     * @return array<string, TaxClassContract>
     */
    public function all(): array
    {
        return $this->classes;
    }
}

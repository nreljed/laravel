<?php

namespace Nreljed\ShopModule\Services;

use Closure;
use InvalidArgumentException;
use Nreljed\ShopModule\Contracts\PaymentDriver;

/**
 * Payment driver registry.
 *
 * Resolves a gateway driver by the storefront's payment-method key
 * (`stripe`, `manual`, ...) using the `shop.payments` config map. Drivers are
 * built lazily so weights stay zero on unrelated requests.
 */
class PaymentManager
{
    /** @var array<string, PaymentDriver> */
    protected array $resolved = [];

    /**
     * @param  array{default?: string, drivers?: array<string, class-string<PaymentDriver>>}  $config
     * @param  Closure(class-string<PaymentDriver>): PaymentDriver|null  $resolver
     */
    public function __construct(
        protected array $config,
        protected ?Closure $resolver = null,
    ) {}

    public function driver(?string $name = null): PaymentDriver
    {
        $name ??= $this->default();

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $class = $this->config['drivers'][$name] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unsupported payment driver [{$name}].");
        }

        $builder = $this->resolver ?? fn (string $class): PaymentDriver => app($class);

        return $this->resolved[$name] = $builder($class);
    }

    public function default(): string
    {
        return $this->config['default'] ?? 'manual';
    }

    public function has(string $name): bool
    {
        return isset($this->config['drivers'][$name]);
    }

    /** @return array<string, string> */
    public function drivers(): array
    {
        return $this->config['drivers'] ?? [];
    }
}

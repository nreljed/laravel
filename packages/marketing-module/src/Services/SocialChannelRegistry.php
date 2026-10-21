<?php

namespace Nreljed\MarketingModule\Services;

use Closure;
use InvalidArgumentException;
use Nreljed\MarketingModule\Contracts\SocialChannelAdapter;

/**
 * Social channel driver registry.
 *
 * Resolves a platform adapter by its key (`meta`, `instagram`, ...) using the
 * `marketing.channels.drivers` config map. Adapters are built lazily so an
 * app that only connected a Meta account never constructs the Pinterest
 * adapter. The shape mirrors the shop module's PaymentManager deliberately:
 * a config-driven map plus a lazily-resolved instance is enough, and a second
 * registry abstraction on top of it would be ceremony.
 */
class SocialChannelRegistry
{
    /** @var array<string, SocialChannelAdapter> */
    protected array $resolved = [];

    /**
     * @param  array{default?: string, drivers?: array<string, class-string<SocialChannelAdapter>>}  $config
     * @param  Closure(class-string<SocialChannelAdapter>): SocialChannelAdapter|null  $resolver
     */
    public function __construct(
        protected array $config,
        protected ?Closure $resolver = null,
    ) {}

    public function driver(?string $name = null): SocialChannelAdapter
    {
        $name ??= $this->default();

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $class = $this->config['drivers'][$name] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("Unsupported social channel [{$name}].");
        }

        $builder = $this->resolver ?? fn (string $class): SocialChannelAdapter => app($class);

        return $this->resolved[$name] = $builder($class);
    }

    public function default(): string
    {
        return $this->config['default'] ?? 'meta';
    }

    public function has(string $name): bool
    {
        return isset($this->config['drivers'][$name]);
    }

    /**
     * The platforms this install can publish to.
     *
     * @return array<string, string> Platform key => adapter class.
     */
    public function drivers(): array
    {
        return $this->config['drivers'] ?? [];
    }

    /**
     * The platform keys, in the order the composer lists them.
     *
     * @return list<string>
     */
    public function platforms(): array
    {
        return array_keys($this->drivers());
    }
}

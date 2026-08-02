<?php

namespace Nreljed\DashboardCore\Ai\Agents\Traits;

use Nreljed\DashboardCore\Ai\Exceptions\MissingAiProviderException;
use Nreljed\DashboardCore\Models\User;

trait ResolvesUserProvider
{
    protected ?User $user = null;

    /** @var array{driver: string, key: string, model: string|null}|null Cached provider data. */
    protected ?array $resolvedProvider = null;

    /**
     * Set the user for BYOK provider resolution.
     */
    public function forUser(User $user): static
    {
        $this->user = $user;
        $this->resolvedProvider = null;

        return $this;
    }

    /**
     * Resolve the config key for the Laravel AI SDK provider resolution.
     */
    public function provider(): string
    {
        $resolved = $this->getResolvedProvider();

        return $resolved['driver'];
    }

    /**
     * Resolve the model name for the current user's active provider.
     */
    public function model(): ?string
    {
        return $this->getResolvedProvider()['model'];
    }

    /**
     * Get the full resolved provider data (cached after first query).
     *
     * @return array{driver: string, key: string, model: string|null}
     */
    public function getResolvedProvider(): array
    {
        if ($this->resolvedProvider !== null) {
            return $this->resolvedProvider;
        }

        $user = $this->user ?? request()->user();

        if (! $user) {
            throw new MissingAiProviderException('unknown');
        }

        $provider = $user->aiProviders()
            ->active()
            ->first();

        if (! $provider) {
            throw new MissingAiProviderException('unknown');
        }

        $this->resolvedProvider = [
            'driver' => $provider->provider,
            'key' => $provider->getDecryptedKey(),
            'model' => $provider->preferred_model,
        ];

        return $this->resolvedProvider;
    }

    /**
     * Inject the BYOK API key into the AI config for the current provider.
     * This is safe in PHP since each request runs in its own process.
     */
    protected function injectProviderKey(): void
    {
        $resolved = $this->getResolvedProvider();

        config()->set("ai.providers.{$resolved['driver']}.key", $resolved['key']);
    }
}

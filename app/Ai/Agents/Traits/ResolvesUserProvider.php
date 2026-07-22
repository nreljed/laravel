<?php

namespace App\Ai\Agents\Traits;

use App\Ai\Exceptions\MissingAiProviderException;
use App\Models\User;

trait ResolvesUserProvider
{
    protected ?User $user = null;

    /** @var array{driver: string, key: string, model: string}|null Cached provider data. */
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
     * Resolve provider configuration for the Laravel AI SDK.
     */
    public function provider(): array
    {
        $resolved = $this->getResolvedProvider();

        return [
            'driver' => $resolved['driver'],
            'key' => $resolved['key'],
        ];
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
     * @return array{driver: string, key: string, model: string}
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
}

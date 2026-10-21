<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\Platform\Models\User;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => 'facebook',
            'account_id' => (string) fake()->unique()->numberBetween(100000, 999999),
            'account_name' => fake()->company(),
            'username' => fake()->userName(),
            'access_token_encrypted' => null,
            'refresh_token_encrypted' => null,
            'scopes' => [],
            'expires_at' => null,
            'status' => SocialAccountStatus::Active,
            'metadata' => [],
            'last_validated_at' => null,
        ];
    }

    /**
     * An account that has completed the OAuth handshake.
     */
    public function connected(): static
    {
        return $this->state(fn (): array => [
            'access_token_encrypted' => 'encrypted-token-placeholder',
            'status' => SocialAccountStatus::Active,
            'last_validated_at' => now(),
        ]);
    }

    /**
     * An account whose grant has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'access_token_encrypted' => 'encrypted-token-placeholder',
            'refresh_token_encrypted' => 'encrypted-refresh-placeholder',
            'expires_at' => now()->subMinute(),
            'status' => SocialAccountStatus::Expired,
        ]);
    }
}

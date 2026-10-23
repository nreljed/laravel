<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\MetricSyncStatus;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;

/**
 * @extends Factory<SocialPostTarget>
 */
class SocialPostTargetFactory extends Factory
{
    protected $model = SocialPostTarget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_post_id' => SocialPost::factory(),
            'social_account_id' => SocialAccount::factory(),
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'last_attempted_at' => null,
            'next_attempt_at' => null,
            'external_post_id' => null,
            'external_url' => null,
            'error_code' => null,
            'error_message' => null,
            'published_at' => null,
            'metrics_status' => MetricSyncStatus::Pending,
            'metrics_attempted_at' => null,
            'metrics_synced_at' => null,
            'metrics_error' => null,
        ];
    }

    /**
     * A delivery that already went out.
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Published,
            'attempts' => 1,
            'last_attempted_at' => now(),
            'external_post_id' => fake()->uuid(),
            'external_url' => fake()->url(),
            'published_at' => now(),
        ]);
    }
}

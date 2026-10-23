<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\CampaignStatus;
use Nreljed\MarketingModule\Models\Campaign;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'objective' => CampaignObjective::Awareness,
            'status' => CampaignStatus::Draft,
            'starts_at' => null,
            'ends_at' => null,
            'metadata' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Active,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);
    }
}

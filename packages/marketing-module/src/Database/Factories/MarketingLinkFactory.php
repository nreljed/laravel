<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\SocialPostTarget;

/**
 * @extends Factory<MarketingLink>
 */
class MarketingLinkFactory extends Factory
{
    protected $model = MarketingLink::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'social_post_id' => null,
            'social_post_target_id' => SocialPostTarget::factory(),
            'destination_url' => fake()->url(),
            'utm_source' => fake()->randomElement(['facebook', 'instagram', 'linkedin', 'x', 'pinterest']),
            'utm_medium' => 'social',
            'utm_campaign' => null,
            'utm_content' => 'post-'.fake()->numberBetween(1, 1000),
            'utm_term' => null,
            'short_code' => Str::lower(Str::random(8)),
            'clicks_count' => 0,
            'last_clicked_at' => null,
            'is_active' => true,
        ];
    }
}

<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\MarketingLinkClick;

/**
 * @extends Factory<MarketingLinkClick>
 */
class MarketingLinkClickFactory extends Factory
{
    protected $model = MarketingLinkClick::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marketing_link_id' => MarketingLink::factory(),
            'clicked_at' => now(),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'user_agent' => fake()->userAgent(),
            'referer' => fake()->url(),
            'country' => null,
        ];
    }
}

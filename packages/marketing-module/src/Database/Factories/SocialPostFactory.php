<?php

namespace Nreljed\MarketingModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\Platform\Models\User;

/**
 * @extends Factory<SocialPost>
 */
class SocialPostFactory extends Factory
{
    protected $model = SocialPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->sentence(12),
            'media' => [],
            'link' => null,
            'hashtags' => [],
            'status' => PostStatus::Draft,
            'scheduled_at' => null,
            'timezone' => 'UTC',
            'requires_approval' => false,
            'approved_at' => null,
            'published_at' => null,
            'metadata' => [],
        ];
    }

    /**
     * A post the scheduler may publish as soon as it is created.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
            'approved_at' => now(),
        ]);
    }

    /**
     * A scheduled post that is still waiting for approval.
     */
    public function awaitingApproval(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Scheduled,
            'scheduled_at' => now()->subMinute(),
            'requires_approval' => true,
            'approved_at' => null,
        ]);
    }
}

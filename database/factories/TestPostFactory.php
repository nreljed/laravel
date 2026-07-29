<?php

namespace Database\Factories;

use App\Models\User;
use App\TestPosts\Models\TestPost;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestPostFactory extends Factory
{
    protected $model = TestPost::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'slug' => fake()->slug(),
            'content' => fake()->paragraphs(3, true),
            'excerpt' => fake()->paragraph(),
            'featured_image' => null,
            'gallery_images' => null,
            'status' => 'draft',
            'published_at' => null,
            'metadata' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }
}

<?php

namespace Nreljed\DashboardCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Models\User;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(6),
            'content' => fake()->paragraphs(3, true),
            'excerpt' => fake()->optional(0.7)->sentence(10),
            'featured_image' => null,
            'status' => 'draft',
            'published_at' => null,
            'metadata' => null,
            'category_id' => null,
            'meta_title' => null,
            'meta_description' => null,
            'og_image' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => 'archived',
            'published_at' => null,
        ]);
    }

    public function future(): static
    {
        return $this->state(fn () => [
            'status' => 'future',
            'published_at' => now()->addWeek(),
        ]);
    }
}

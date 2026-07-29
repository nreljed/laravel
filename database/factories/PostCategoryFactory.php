<?php

namespace Database\Factories;

use App\Blog\Models\PostCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostCategory>
 */
class PostCategoryFactory extends Factory
{
    protected $model = PostCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional(0.6)->sentence(),
            'parent_id' => null,
            'order' => fake()->numberBetween(0, 100),
        ];
    }
}

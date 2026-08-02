<?php

namespace Nreljed\DashboardCore\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Nreljed\DashboardCore\Blog\Models\PostTag;

/**
 * @extends Factory<PostTag>
 */
class PostTagFactory extends Factory
{
    protected $model = PostTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
        ];
    }
}

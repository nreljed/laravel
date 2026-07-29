<?php

namespace Database\Factories;

use App\Blog\Models\PostTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PostTag>
 */
class PostTagFactory extends Factory
{
    protected $model = PostTag::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
        ];
    }
}

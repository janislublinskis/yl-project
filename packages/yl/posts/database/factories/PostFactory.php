<?php

namespace Yl\Posts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Yl\Posts\Models\Post;

/**
 * PostFactory
 *
 * Generates realistic fake Post records for seeding and testing.
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'title'        => $title,
            'slug'         => Str::slug($title) . '-' . $this->faker->unique()->randomNumber(4),
            'body'         => $this->faker->paragraphs(4, true),
            'status'       => $this->faker->randomElement(Post::STATUSES),
            'published_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state([
            'status'       => 'draft',
            'published_at' => null,
        ]);
    }
}

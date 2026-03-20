<?php

namespace Yl\Products\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Yl\Products\Models\Product;

/**
 * ProductFactory
 *
 * Generates realistic fake Product records for seeding and testing.
 *
 * Usage:
 *   Product::factory()->create();
 *   Product::factory()->inactive()->count(5)->create();
 */
class ProductFactory extends Factory
{
    /** Tell Laravel which model this factory builds. */
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name'        => $this->faker->unique()->words(3, true),
            'description' => $this->faker->optional()->paragraph(),
            'price'       => $this->faker->randomFloat(2, 0.99, 999.99),
            'stock'       => $this->faker->numberBetween(0, 500),
            'status'      => $this->faker->randomElement(Product::STATUSES),
        ];
    }

    /** State: force the product to be active. */
    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    /** State: force the product to be inactive. */
    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    /** State: zero stock. */
    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }
}

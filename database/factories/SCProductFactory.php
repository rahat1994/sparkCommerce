<?php

namespace Rahat1994\SparkCommerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Rahat1994\SparkCommerce\Models\SCProduct;

class SCProductFactory extends Factory
{
    protected $model = SCProduct::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'product_type' => 'simple',
            'regular_price' => fake()->randomFloat(2, 5, 100),
            'sale_price' => null,
            'sku' => fake()->unique()->bothify('SKU-####??'),
            'stock_quantity' => 10,
            'should_allow_backorders' => 'do_not_allow',
            'description' => fake()->paragraph(),
            'product_attributes' => [],
        ];
    }

    public function variable(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_type' => 'variable',
            'regular_price' => null,
        ]);
    }
}

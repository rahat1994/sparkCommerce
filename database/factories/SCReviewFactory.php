<?php

namespace Rahat1994\SparkCommerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rahat1994\SparkCommerce\Models\SCProduct;
use Rahat1994\SparkCommerce\Models\SCReview;

class SCReviewFactory extends Factory
{
    protected $model = SCReview::class;

    public function definition(): array
    {
        return [
            'product_id' => SCProduct::factory(),
            'title' => fake()->sentence(3),
            'content' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
        ];
    }
}

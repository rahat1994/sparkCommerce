<?php

namespace Rahat1994\SparkCommerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rahat1994\SparkCommerce\Models\SCCoupon;

class SCCouponFactory extends Factory
{
    protected $model = SCCoupon::class;

    public function definition(): array
    {
        return [
            'coupon_code' => strtoupper(fake()->unique()->bothify('SAVE##??')),
            'coupon_type' => 'fixed',
            'coupon_amount' => 5,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonth(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subMonth(),
            'end_date' => now()->subDay(),
        ]);
    }
}

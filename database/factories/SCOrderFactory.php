<?php

namespace Rahat1994\SparkCommerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Rahat1994\SparkCommerce\Models\SCOrder;

class SCOrderFactory extends Factory
{
    protected $model = SCOrder::class;

    public function definition(): array
    {
        return [
            'items' => [],
            'shipping_address' => json_encode(['address' => fake()->streetAddress()]),
            'billing_address' => json_encode(['address' => fake()->streetAddress()]),
            'total_amount' => fake()->randomFloat(2, 10, 500),
            'tracking_number' => (string) Str::ulid(),
            'order_number' => fake()->unique()->numerify('ORD-######'),
            'status' => 'pending',
            'payment_status' => null,
            'user_id' => null,
            'meta' => [],
        ];
    }

    public function awaitingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'awaiting_payment',
            'payment_status' => 'pending',
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'payment_status' => 'paid',
        ]);
    }
}

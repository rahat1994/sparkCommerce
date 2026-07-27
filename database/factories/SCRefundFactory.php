<?php

namespace Rahat1994\SparkCommerce\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Rahat1994\SparkCommerce\Enums\RefundStatus;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCRefund;

class SCRefundFactory extends Factory
{
    protected $model = SCRefund::class;

    public function definition(): array
    {
        return [
            'order_id' => SCOrder::factory(),
            'gateway' => 'fake',
            'gateway_refund_id' => null,
            'amount_cents' => fake()->numberBetween(100, 5000),
            'currency' => 'USD',
            'status' => RefundStatus::Pending,
            'restocked' => false,
            'initiated_by' => null,
            'failure_reason' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Succeeded,
            'gateway_refund_id' => 'fake_re_' . fake()->unique()->numberBetween(1, 100000),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Failed,
            'failure_reason' => 'card_network_declined',
        ]);
    }
}

<?php

use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCRefund;
use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;
use Rahat1994\SparkCommerce\Services\RefundService;

it('records a distinct gateway reference per refund on the same order', function () {
    FakeGateway::reset();

    $order = SCOrder::factory()->paid()->create([
        'total_amount_cents' => 2000,
        'currency' => 'USD',
        'payment_gateway' => 'fake',
        'transaction_id' => 'fake_pi_test',
        'items' => [],
    ]);

    $service = app(RefundService::class);

    $service->refund($order, 500, initiatedBy: null, restock: false);
    $service->refund($order->fresh(), 1500, initiatedBy: null, restock: false);

    $references = SCRefund::query()
        ->where('order_id', $order->id)
        ->pluck('gateway_refund_id');

    expect($references)->toHaveCount(2)
        ->and($references->unique())->toHaveCount(2);
});

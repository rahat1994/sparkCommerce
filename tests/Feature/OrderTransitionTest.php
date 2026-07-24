<?php

use Illuminate\Support\Facades\Event;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Events\OrderTransitioned;
use Rahat1994\SparkCommerce\Events\OrderTransitioning;
use Rahat1994\SparkCommerce\Exceptions\IllegalOrderTransition;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Services\OrderTransitionService;

dataset('legal transitions', [
    'awaiting_payment to paid' => ['awaiting_payment', OrderStatus::Paid],
    'awaiting_payment to expired' => ['awaiting_payment', OrderStatus::Expired],
    'awaiting_payment to cancelled' => ['awaiting_payment', OrderStatus::Cancelled],
    'paid to processing' => ['paid', OrderStatus::Processing],
    'paid to cancelled' => ['paid', OrderStatus::Cancelled],
    'paid to refunded' => ['paid', OrderStatus::Refunded],
    'processing to shipped' => ['processing', OrderStatus::Shipped],
    'processing to refunded' => ['processing', OrderStatus::Refunded],
    'shipped to delivered' => ['shipped', OrderStatus::Delivered],
]);

dataset('illegal transitions', [
    'shipped to awaiting_payment' => ['shipped', OrderStatus::AwaitingPayment],
    'expired to paid' => ['expired', OrderStatus::Paid],
    'delivered to awaiting_payment' => ['delivered', OrderStatus::AwaitingPayment],
    'delivered to paid' => ['delivered', OrderStatus::Paid],
    'delivered to processing' => ['delivered', OrderStatus::Processing],
    'delivered to shipped' => ['delivered', OrderStatus::Shipped],
    'delivered to cancelled' => ['delivered', OrderStatus::Cancelled],
    'delivered to expired' => ['delivered', OrderStatus::Expired],
    'delivered to refunded' => ['delivered', OrderStatus::Refunded],
    'awaiting_payment to processing' => ['awaiting_payment', OrderStatus::Processing],
]);

it('encodes the exact transition machine on the enum', function () {
    $expected = [
        'awaiting_payment' => ['paid', 'expired', 'cancelled'],
        'paid' => ['processing', 'cancelled', 'refunded'],
        'processing' => ['shipped', 'refunded'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'expired' => [],
        'refunded' => [],
    ];

    foreach (OrderStatus::cases() as $from) {
        $allowed = collect(OrderStatus::cases())
            ->filter(fn (OrderStatus $to): bool => $from->canTransitionTo($to))
            ->map(fn (OrderStatus $to): string => $to->value)
            ->values()
            ->all();

        expect($allowed)->toEqualCanonicalizing($expected[$from->value]);
    }
});

it('performs every legal transition through the service', function (string $from, OrderStatus $to) {
    $order = SCOrder::factory()->create(['status' => $from]);

    $result = app(OrderTransitionService::class)->transition($order, $to);

    expect($result->status)->toBe($to)
        ->and($order->fresh()->status)->toBe($to);
})->with('legal transitions');

it('throws IllegalOrderTransition naming from and to on illegal moves', function (string $from, OrderStatus $to) {
    $order = SCOrder::factory()->create(['status' => $from]);

    try {
        app(OrderTransitionService::class)->transition($order, $to);

        $this->fail('Expected IllegalOrderTransition to be thrown.');
    } catch (IllegalOrderTransition $exception) {
        expect($exception->getMessage())
            ->toContain($from)
            ->toContain($to->value);
    }

    expect($order->fresh()->status->value)->toBe($from);
})->with('illegal transitions');

it('dispatches OrderTransitioning and OrderTransitioned with order, from and to', function () {
    Event::fake([OrderTransitioning::class, OrderTransitioned::class]);

    $order = SCOrder::factory()->awaitingPayment()->create();

    app(OrderTransitionService::class)->transition($order, OrderStatus::Paid);

    Event::assertDispatched(
        OrderTransitioning::class,
        fn (OrderTransitioning $event): bool => $event->order->is($order)
            && $event->from === OrderStatus::AwaitingPayment
            && $event->to === OrderStatus::Paid
    );

    Event::assertDispatched(
        OrderTransitioned::class,
        fn (OrderTransitioned $event): bool => $event->order->is($order)
            && $event->from === OrderStatus::AwaitingPayment
            && $event->to === OrderStatus::Paid
    );
});

it('aborts the transition when an OrderTransitioning listener throws', function () {
    Event::listen(OrderTransitioning::class, function (): void {
        throw new RuntimeException('veto');
    });

    $transitionedFired = false;

    Event::listen(OrderTransitioned::class, function () use (&$transitionedFired): void {
        $transitionedFired = true;
    });

    $order = SCOrder::factory()->awaitingPayment()->create();

    expect(fn () => app(OrderTransitionService::class)->transition($order, OrderStatus::Paid))
        ->toThrow(RuntimeException::class, 'veto');

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($transitionedFired)->toBeFalse();
});

it('fires OrderTransitioned only after the status write is committed', function () {
    $statusSeenByListener = null;

    Event::listen(OrderTransitioned::class, function (OrderTransitioned $event) use (&$statusSeenByListener): void {
        $statusSeenByListener = $event->order->fresh()->status;
    });

    $order = SCOrder::factory()->awaitingPayment()->create();

    app(OrderTransitionService::class)->transition($order, OrderStatus::Paid);

    expect($statusSeenByListener)->toBe(OrderStatus::Paid);
});

it('writes payment_status when the context provides one', function () {
    $order = SCOrder::factory()->awaitingPayment()->create();

    app(OrderTransitionService::class)->transition($order, OrderStatus::Paid, [
        'payment_status' => PaymentStatus::Paid,
    ]);

    $fresh = $order->fresh();

    expect($fresh->status)->toBe(OrderStatus::Paid)
        ->and($fresh->payment_status)->toBe(PaymentStatus::Paid);
});

it('accepts a payment_status string in the context and keeps null untouched', function () {
    $order = SCOrder::factory()->create([
        'status' => OrderStatus::AwaitingPayment,
        'payment_status' => null,
    ]);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Cancelled);

    expect($order->fresh()->payment_status)->toBeNull();

    $other = SCOrder::factory()->awaitingPayment()->create();

    app(OrderTransitionService::class)->transition($other, OrderStatus::Paid, [
        'payment_status' => 'paid',
    ]);

    expect($other->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('reads shipping_status through the order status', function () {
    $order = SCOrder::factory()->paid()->create();

    expect($order->shipping_status)->toBe(OrderStatus::Paid);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Processing);

    expect($order->fresh()->shipping_status)->toBe(OrderStatus::Processing);
});

<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages\ListOrders;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;

beforeEach(function () {
    $this->actingAs($this->createAdminUser());

    Filament::setCurrentPanel('admin');
});

it('rejects accepting an order that is still awaiting payment', function () {
    $order = SCOrder::factory()->awaitingPayment()->create();

    Livewire::test(ListOrders::class)
        ->callAction(
            TestAction::make('Confirm Order')->table($order),
            ['status' => OrderStatus::Processing->value],
        )
        ->assertNotified();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

it('advances a paid order to processing through the transition service', function () {
    $order = SCOrder::factory()->paid()->create();

    Livewire::test(ListOrders::class)
        ->callAction(
            TestAction::make('Confirm Order')->table($order),
            ['status' => OrderStatus::Processing->value],
        );

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('cancels an awaiting-payment order and cancels the gateway payment first', function () {
    $order = SCOrder::factory()->awaitingPayment()->create([
        'payment_gateway' => 'fake',
        'transaction_id' => 'fake_pi_admin_cancel',
    ]);

    Livewire::test(ListOrders::class)
        ->callAction(TestAction::make('cancelOrder')->table($order));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        // The gateway payment is cancelled before the order transitions,
        // so a live PaymentIntent is never orphaned.
        ->and(FakeGateway::calls('cancelPayment'))->toHaveCount(1);
});

it('does not cancel the order when the gateway refuses to cancel the payment', function () {
    $order = SCOrder::factory()->awaitingPayment()->create([
        'payment_gateway' => 'fake',
        'transaction_id' => 'fake_pi_processing',
    ]);

    // A payment already processing cannot be cancelled at the processor.
    FakeGateway::refuseCancellation();

    Livewire::test(ListOrders::class)
        ->callAction(TestAction::make('cancelOrder')->table($order))
        ->assertNotified();

    // The order is left awaiting payment: never cancel an order whose
    // charge could not be cancelled.
    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(FakeGateway::calls('cancelPayment'))->toHaveCount(1);
});

it('skips the gateway when a free order without a gateway is cancelled', function () {
    $order = SCOrder::factory()->awaitingPayment()->create([
        'payment_gateway' => null,
        'transaction_id' => null,
    ]);

    Livewire::test(ListOrders::class)
        ->callAction(TestAction::make('cancelOrder')->table($order));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(FakeGateway::calls('cancelPayment'))->toHaveCount(0);
});

it('hides the cancel action for a paid order so it is refunded, not stranded', function () {
    $order = SCOrder::factory()->paid()->create();

    Livewire::test(ListOrders::class)
        ->assertActionHidden(TestAction::make('cancelOrder')->table($order));
});

it('hides the cancel action for a delivered order', function () {
    $order = SCOrder::factory()->create(['status' => OrderStatus::Delivered]);

    Livewire::test(ListOrders::class)
        ->assertActionHidden(TestAction::make('cancelOrder')->table($order));
});

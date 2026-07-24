<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages\ListOrders;
use Rahat1994\SparkCommerce\Models\SCOrder;

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

it('cancels a paid order through the transition service', function () {
    $order = SCOrder::factory()->paid()->create();

    Livewire::test(ListOrders::class)
        ->callAction(TestAction::make('cancelOrder')->table($order));

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('rejects cancelling a delivered order', function () {
    $order = SCOrder::factory()->create(['status' => OrderStatus::Delivered]);

    Livewire::test(ListOrders::class)
        ->callAction(TestAction::make('cancelOrder')->table($order))
        ->assertNotified();

    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

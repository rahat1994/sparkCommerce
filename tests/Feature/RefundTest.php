<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Enums\RefundStatus;
use Rahat1994\SparkCommerce\Events\DisputeCreated;
use Rahat1994\SparkCommerce\Events\OrderAutoRefunded;
use Rahat1994\SparkCommerce\Events\RefundFailed;
use Rahat1994\SparkCommerce\Exceptions\RefundGatewayFailed;
use Rahat1994\SparkCommerce\Exceptions\RefundNotAllowed;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource\Pages\ListOrders;
use Rahat1994\SparkCommerce\Jobs\HandleChargeRefunded;
use Rahat1994\SparkCommerce\Jobs\HandleDisputeCreated;
use Rahat1994\SparkCommerce\Jobs\HandlePaymentIntentSucceeded;
use Rahat1994\SparkCommerce\Jobs\HandleRefundFailed;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCProduct;
use Rahat1994\SparkCommerce\Models\SCRefund;
use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;
use Rahat1994\SparkCommerce\Services\OrderTransitionService;
use Rahat1994\SparkCommerce\Services\RefundService;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;

function refundService(): RefundService
{
    return app(RefundService::class);
}

function refundClaimsTable(): string
{
    return config('sparkcommerce.table_prefix') . config('sparkcommerce.payment_events_table_name');
}

/**
 * A paid order on the fake gateway carrying a snapshot of two units of the
 * given product (2000 cents total), the way checkout + the paid webhook
 * leave it.
 *
 * @param  array<string, mixed>  $attributes
 */
function refundOrder(SCProduct $product, array $attributes = []): SCOrder
{
    return SCOrder::factory()->paid()->create([
        'currency' => 'USD',
        'total_amount_cents' => 2000,
        'transaction_id' => 'fake_pi_refund',
        'payment_gateway' => 'fake',
        'items' => [[
            'itemable_type' => SCProduct::class,
            'itemable_id' => $product->id,
            'quantity' => 2,
            'unit_amount' => 1000,
            'name' => $product->name,
        ]],
        ...$attributes,
    ]);
}

it('refunds a paid order in full with a row-derived idempotency key, restock, and derived statuses', function () {
    $admin = $this->createAdminUser();
    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    $refund = refundService()->refund($order, 2000, initiatedBy: $admin->id);

    expect(FakeGateway::calls('refund'))->toHaveCount(1)
        ->and(FakeGateway::calls('refund')[0]['idempotency_key'])->toBe("refund-{$refund->getKey()}")
        ->and(FakeGateway::calls('refund')[0]['amount_cents'])->toBe(2000)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->gateway_refund_id)->toBe('fake_re_' . $order->getKey())
        ->and($refund->restocked)->toBeTrue()
        ->and($refund->initiated_by)->toBe($admin->id)
        // The two snapshot units go back on the shelf.
        ->and($product->fresh()->stock_quantity)->toBe(7)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

it('partially refunds without touching the fulfillment status or the stock', function () {
    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    $refund = refundService()->refund($order, 500, initiatedBy: null, restock: false);

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->restocked)->toBeFalse()
        ->and($product->fresh()->stock_quantity)->toBe(5)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded);
});

it('rejects a second partial refund exceeding the remaining balance without calling the gateway', function () {
    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    refundService()->refund($order, 1500, initiatedBy: null, restock: false);

    expect(fn () => refundService()->refund($order, 600, initiatedBy: null, restock: false))
        ->toThrow(RefundNotAllowed::class);

    expect(FakeGateway::calls('refund'))->toHaveCount(1)
        ->and(SCRefund::query()->where('order_id', $order->getKey())->count())->toBe(1)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded);
});

it('rejects the second of two rapid full-refund submissions', function () {
    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    refundService()->refund($order, 2000, initiatedBy: null);

    expect(fn () => refundService()->refund($order, 2000, initiatedBy: null))
        ->toThrow(RefundNotAllowed::class);

    expect(FakeGateway::calls('refund'))->toHaveCount(1)
        ->and(SCRefund::query()->where('order_id', $order->getKey())->count())->toBe(1)
        // Restocked exactly once by the first submission.
        ->and($product->fresh()->stock_quantity)->toBe(7);
});

it('counts pending refunds in the over-refund guard so an in-flight refund blocks a double submit', function () {
    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    // A full-amount refund stuck pending (e.g. the process died between the
    // row commit and the gateway answer).
    SCRefund::factory()->create([
        'order_id' => $order->getKey(),
        'gateway' => 'fake',
        'amount_cents' => 2000,
        'currency' => 'USD',
        'status' => RefundStatus::Pending,
    ]);

    expect(fn () => refundService()->refund($order, 2000, initiatedBy: null))
        ->toThrow(RefundNotAllowed::class);

    expect(FakeGateway::calls('refund'))->toHaveCount(0);
});

it('rejects refunding an order that is still awaiting payment', function () {
    $order = SCOrder::factory()->awaitingPayment()->create([
        'currency' => 'USD',
        'total_amount_cents' => 2000,
        'transaction_id' => 'fake_pi_unpaid',
        'payment_gateway' => 'fake',
    ]);

    expect(fn () => refundService()->refund($order, 2000, initiatedBy: null))
        ->toThrow(RefundNotAllowed::class);

    expect(FakeGateway::calls('refund'))->toHaveCount(0)
        ->and(SCRefund::query()->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

it('rejects refunding an expired order unless the late-payment path explicitly allows it', function () {
    $order = SCOrder::factory()->create([
        'status' => OrderStatus::Expired,
        'payment_status' => PaymentStatus::Pending,
        'currency' => 'USD',
        'total_amount_cents' => 2000,
        'transaction_id' => 'fake_pi_expired',
        'payment_gateway' => 'fake',
    ]);

    expect(fn () => refundService()->refund($order, 2000, initiatedBy: null))
        ->toThrow(RefundNotAllowed::class);

    expect(FakeGateway::calls('refund'))->toHaveCount(0)
        ->and(SCRefund::query()->count())->toBe(0);
});

it('marks the refund failed with the gateway reason and leaves the order untouched when the gateway throws', function () {
    Event::fake([RefundFailed::class]);

    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    FakeGateway::failRefunds('card_network_declined');

    expect(fn () => refundService()->refund($order, 2000, initiatedBy: null))
        ->toThrow(RefundGatewayFailed::class);

    $refund = SCRefund::query()->where('order_id', $order->getKey())->sole();

    expect($refund->status)->toBe(RefundStatus::Failed)
        ->and($refund->failure_reason)->toBe('card_network_declined')
        ->and($refund->restocked)->toBeFalse()
        ->and($product->fresh()->stock_quantity)->toBe(5)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);

    Event::assertDispatched(
        RefundFailed::class,
        fn (RefundFailed $event): bool => $event->refund->is($refund)
    );
});

it('treats charge.refunded redelivery for an already-succeeded refund as a no-op: stock restored exactly once', function () {
    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    // Synchronous authority: the admin refund already restocked (5 -> 7).
    $refund = refundService()->refund($order, 2000, initiatedBy: null);

    $event = [
        'id' => 'evt_fake_charge_refunded_1',
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id' => 'ch_fake_1',
            'object' => 'charge',
            'payment_intent' => 'fake_pi_refund',
            'amount_refunded' => 2000,
            'refunds' => ['object' => 'list', 'data' => [
                ['id' => $refund->gateway_refund_id, 'object' => 'refund', 'status' => 'succeeded'],
            ]],
        ]],
    ];

    (new HandleChargeRefunded('fake', $event))->handle(refundService());
    // Redelivery of the exact same event id: the claim blocks it.
    (new HandleChargeRefunded('fake', $event))->handle(refundService());

    expect($product->fresh()->stock_quantity)->toBe(7)
        ->and(DB::table(refundClaimsTable())->where('event_id', 'evt_fake_charge_refunded_1')->count())->toBe(1)
        ->and($refund->fresh()->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->fresh()->restocked)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

it('flips a pending refund to failed with the gateway reason on a refund.failed webhook', function () {
    Event::fake([RefundFailed::class]);

    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    // A refund the gateway accepted but later could not complete.
    $refund = SCRefund::factory()->create([
        'order_id' => $order->getKey(),
        'gateway' => 'fake',
        'gateway_refund_id' => 'fake_re_async_1',
        'amount_cents' => 2000,
        'currency' => 'USD',
        'status' => RefundStatus::Pending,
    ]);

    $event = [
        'id' => 'evt_fake_refund_failed_1',
        'type' => 'refund.failed',
        'data' => ['object' => [
            'id' => 'fake_re_async_1',
            'object' => 'refund',
            'status' => 'failed',
            'failure_reason' => 'insufficient_funds',
            'payment_intent' => 'fake_pi_refund',
        ]],
    ];

    (new HandleRefundFailed('fake', $event))->handle(refundService());
    // Redelivery of the exact same event id: the claim blocks it.
    (new HandleRefundFailed('fake', $event))->handle(refundService());

    expect($refund->fresh()->status)->toBe(RefundStatus::Failed)
        ->and($refund->fresh()->failure_reason)->toBe('insufficient_funds')
        ->and(DB::table(refundClaimsTable())->where('event_id', 'evt_fake_refund_failed_1')->count())->toBe(1)
        // The fulfillment side never moves on a failed refund.
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);

    Event::assertDispatchedTimes(RefundFailed::class, 1);
    Event::assertDispatched(
        RefundFailed::class,
        fn (RefundFailed $event): bool => $event->refund->is($refund)
    );
});

it('flags the order disputed and dispatches DisputeCreated on charge.dispute.created', function () {
    Event::fake([DisputeCreated::class]);

    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    $event = [
        'id' => 'evt_fake_dispute_1',
        'type' => 'charge.dispute.created',
        'data' => ['object' => [
            'id' => 'dp_fake_1',
            'object' => 'dispute',
            'payment_intent' => 'fake_pi_refund',
            'reason' => 'fraudulent',
        ]],
    ];

    (new HandleDisputeCreated('fake', $event))->handle();
    // Redelivery of the exact same event id: the claim blocks it.
    (new HandleDisputeCreated('fake', $event))->handle();

    expect($order->fresh()->meta['payment_flag'] ?? null)->toBe('disputed')
        // A dispute never moves the order; it is resolved at the processor.
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(DB::table(refundClaimsTable())->where('event_id', 'evt_fake_dispute_1')->count())->toBe(1);

    Event::assertDispatchedTimes(DisputeCreated::class, 1);
    Event::assertDispatched(
        DisputeCreated::class,
        fn (DisputeCreated $event): bool => $event->order->is($order) && ($event->dispute['id'] ?? null) === 'dp_fake_1'
    );
});

it('automatically refunds a payment that succeeds after the order expired, without restocking again', function () {
    Event::fake([OrderAutoRefunded::class]);

    // The expiry sweep already released the reservation: 7 on hand.
    $product = SCProduct::factory()->managedStock(7)->create();
    $order = refundOrder($product, [
        'status' => OrderStatus::Expired,
        'payment_status' => PaymentStatus::Pending,
        'transaction_id' => 'fake_pi_late',
        'expires_at' => now()->subHour(),
    ]);

    (new HandlePaymentIntentSucceeded('fake', [
        'id' => 'evt_fake_late_1',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => [
            'id' => 'fake_pi_late',
            'amount_received' => 2000,
            'currency' => 'usd',
        ]],
    ]))->handle(app(OrderTransitionService::class));

    $refund = SCRefund::query()->where('order_id', $order->getKey())->sole();

    expect($refund->initiated_by)->toBeNull()
        ->and((int) $refund->getRawOriginal('amount_cents'))->toBe(2000)
        ->and($refund->status)->toBe(RefundStatus::Succeeded)
        // The expiry transition already released the stock; never twice.
        ->and($refund->restocked)->toBeFalse()
        ->and($product->fresh()->stock_quantity)->toBe(7)
        ->and($order->fresh()->status)->toBe(OrderStatus::Expired)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->meta['payment_flag'] ?? null)->toBe('paid_after_expiry')
        ->and(FakeGateway::calls('refund'))->toHaveCount(1);

    Event::assertDispatched(
        OrderAutoRefunded::class,
        fn (OrderAutoRefunded $event): bool => $event->order->is($order) && $event->refund->is($refund)
    );
});

it('lets an admin refund a paid order in full from the orders table', function () {
    $admin = $this->createAdminUser();
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    Livewire::test(ListOrders::class)
        ->callAction(
            TestAction::make('refund')->table($order),
            ['amount' => 20, 'restock' => true],
        )
        ->assertNotified('Refund processed');

    $refund = SCRefund::query()->where('order_id', $order->getKey())->sole();

    expect($refund->status)->toBe(RefundStatus::Succeeded)
        ->and($refund->initiated_by)->toBe($admin->id)
        ->and((int) $refund->getRawOriginal('amount_cents'))->toBe(2000)
        ->and($refund->restocked)->toBeTrue()
        ->and($product->fresh()->stock_quantity)->toBe(7)
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
});

it('surfaces a gateway refusal as a danger notification instead of a crash', function () {
    $this->actingAs($this->createAdminUser());
    Filament::setCurrentPanel('admin');

    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    FakeGateway::failRefunds('processor_unavailable');

    Livewire::test(ListOrders::class)
        ->callAction(
            TestAction::make('refund')->table($order),
            ['amount' => 20, 'restock' => false],
        )
        ->assertNotified('Refund was not processed');

    expect(SCRefund::query()->sole()->status)->toBe(RefundStatus::Failed)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('hides the refund action for an order that is not refundable', function () {
    $this->actingAs($this->createAdminUser());
    Filament::setCurrentPanel('admin');

    $order = SCOrder::factory()->awaitingPayment()->create([
        'currency' => 'USD',
        'total_amount_cents' => 2000,
        'payment_gateway' => 'fake',
    ]);

    Livewire::test(ListOrders::class)
        ->assertActionHidden(TestAction::make('refund')->table($order));
});

it('denies the refund surface to a user without the admin role', function () {
    $this->actingAs(User::create([
        'name' => 'Customer',
        'email' => 'customer@example.com',
        'password' => bcrypt('password'),
    ]));

    Filament::setCurrentPanel('admin');

    $product = SCProduct::factory()->managedStock(5)->create();
    $order = refundOrder($product);

    Livewire::test(ListOrders::class)->assertForbidden();

    expect(SCRefund::query()->count())->toBe(0)
        ->and(FakeGateway::calls('refund'))->toHaveCount(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
});

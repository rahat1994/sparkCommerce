<?php

use Binafy\LaravelCart\Models\Cart;
use Binafy\LaravelCart\Models\CartItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Enums\RefundStatus;
use Rahat1994\SparkCommerce\Events\DisputeCreated;
use Rahat1994\SparkCommerce\Events\LatePaymentReceived;
use Rahat1994\SparkCommerce\Events\PaymentAmountMismatch;
use Rahat1994\SparkCommerce\Events\RefundFailed;
use Rahat1994\SparkCommerce\Events\WebhookSignatureFailing;
use Rahat1994\SparkCommerce\Models\SCCoupon;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCProduct;
use Rahat1994\SparkCommerce\Models\SCRefund;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;

function stripeWebhookUser(): User
{
    return User::create([
        'name' => 'Webhook Shopper',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('secret-password'),
    ]);
}

function stripeWebhookEventsTable(): string
{
    return config('sparkcommerce.table_prefix') . config('sparkcommerce.payment_events_table_name');
}

function stripeWebhookPivotTable(): string
{
    return config('sparkcommerce.table_prefix') . config('sparkcommerce.coupon_user_table_name');
}

/**
 * An order the way checkout + createPayment leave it: awaiting payment
 * with the PaymentIntent id stored as transaction_id.
 *
 * @param  array<string, mixed>  $attributes
 */
function stripeWebhookOrder(User $user, array $attributes = []): SCOrder
{
    return SCOrder::factory()->awaitingPayment()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
        'total_amount_cents' => 1999,
        'transaction_id' => 'pi_test_123',
        'payment_gateway' => 'stripe',
        'expires_at' => now()->addMinutes(90),
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $object  the payment_intent object
 * @return array<string, mixed>
 */
function stripeWebhookEvent(string $type, array $object, string $eventId = 'evt_test_1'): array
{
    return [
        'id' => $eventId,
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => $object],
    ];
}

/**
 * @return array<string, mixed>
 */
function stripePaymentIntent(SCOrder $order, array $overrides = []): array
{
    return [
        'id' => $order->transaction_id,
        'object' => 'payment_intent',
        'amount' => (int) $order->getRawOriginal('total_amount_cents'),
        'amount_received' => (int) $order->getRawOriginal('total_amount_cents'),
        'currency' => strtolower((string) $order->currency),
        'metadata' => [
            'order_id' => (string) $order->getKey(),
            'tracking_number' => (string) $order->tracking_number,
        ],
        ...$overrides,
    ];
}

/**
 * Real Stripe signature scheme: v1 = HMAC-SHA256("{t}.{payload}", secret).
 */
function stripeSignatureHeader(string $payload, ?string $secret = null, ?int $timestamp = null): string
{
    $secret ??= (string) config('sparkcommerce.payments.gateways.stripe.webhook_secret');
    $timestamp ??= time();

    $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

    return "t={$timestamp},v1={$signature}";
}

/**
 * POST the raw payload to the stripe webhook endpoint, properly signed
 * unless an explicit (tampered) signature is given.
 *
 * @param  array<string, mixed>  $event
 */
function postStripeWebhook(array $event, ?string $signature = null): TestResponse
{
    $payload = json_encode($event);

    return test()->call('POST', '/sc/webhooks/stripe', server: [
        'HTTP_STRIPE_SIGNATURE' => $signature ?? stripeSignatureHeader($payload),
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload);
}

it('marks the order paid, deletes the cart, and records coupon usage on a signed payment_intent.succeeded', function () {
    $user = stripeWebhookUser();
    $product = SCProduct::factory()->create(['regular_price' => 19.99, 'sale_price' => null]);

    // Multi-use coupon: usage records at paid, exactly here.
    $coupon = SCCoupon::factory()->create([
        'coupon_type' => 'percentage_discount',
        'coupon_amount' => 10,
        'usage_limit' => 5,
    ]);

    $order = stripeWebhookOrder($user, [
        'discount' => [
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->coupon_code,
            'reserved' => false,
        ],
    ]);

    // The shopper's cart, as checkout reads it, still holds the items.
    $cart = Cart::query()->create(['user_id' => $user->id]);
    $cart->items()->save(new CartItem([
        'itemable_id' => $product->id,
        'itemable_type' => SCProduct::class,
        'quantity' => 1,
    ]));

    postStripeWebhook(stripeWebhookEvent('payment_intent.succeeded', stripePaymentIntent($order)))
        ->assertSuccessful();

    $fresh = $order->fresh();

    expect($fresh->status)->toBe(OrderStatus::Paid)
        ->and($fresh->payment_status)->toBe(PaymentStatus::Paid)
        // The claim row proves the event was recorded.
        ->and(DB::table(stripeWebhookEventsTable())->where('gateway', 'stripe')->where('event_id', 'evt_test_1')->count())->toBe(1)
        // The U11 boundary: the paid webhook deletes the shopper's cart.
        ->and(Cart::query()->where('user_id', $user->id)->exists())->toBeFalse();

    $pivotRow = DB::table(stripeWebhookPivotTable())
        ->where('coupon_id', $coupon->id)
        ->where('user_id', $user->id)
        ->first();

    expect($pivotRow)->not->toBeNull()
        ->and((int) $pivotRow->usage_count)->toBe(1)
        ->and($coupon->fresh()->usage_count)->toBe(1);
});

it('treats a duplicate delivery as a no-op with a single claim row and no double coupon usage', function () {
    $user = stripeWebhookUser();

    $coupon = SCCoupon::factory()->create([
        'coupon_type' => 'percentage_discount',
        'coupon_amount' => 10,
        'usage_limit' => 5,
    ]);

    $order = stripeWebhookOrder($user, [
        'discount' => [
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->coupon_code,
            'reserved' => false,
        ],
    ]);

    $event = stripeWebhookEvent('payment_intent.succeeded', stripePaymentIntent($order));

    postStripeWebhook($event)->assertSuccessful();
    // Stripe redelivers the exact same event id.
    postStripeWebhook($event)->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(DB::table(stripeWebhookEventsTable())->count())->toBe(1)
        ->and($coupon->fresh()->usage_count)->toBe(1)
        ->and((int) DB::table(stripeWebhookPivotTable())
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->id)
            ->value('usage_count'))->toBe(1);
});

it('rejects a tampered signature with 400 before any claim or side effect', function () {
    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user);

    $event = stripeWebhookEvent('payment_intent.succeeded', stripePaymentIntent($order));
    $payload = json_encode($event);

    postStripeWebhook($event, stripeSignatureHeader($payload, 'whsec_wrong_secret'))
        ->assertStatus(400);

    expect(DB::table(stripeWebhookEventsTable())->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('dispatches WebhookSignatureFailing once when signature failures reach five in an hour', function () {
    Event::fake([WebhookSignatureFailing::class]);

    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user);

    $event = stripeWebhookEvent('payment_intent.succeeded', stripePaymentIntent($order));
    $payload = json_encode($event);

    foreach (range(1, 6) as $i) {
        postStripeWebhook($event, stripeSignatureHeader($payload, 'whsec_wrong_secret'))
            ->assertStatus(400);
    }

    Event::assertDispatchedTimes(WebhookSignatureFailing::class, 1);
});

it('flags an amount mismatch without transitioning the order', function () {
    Event::fake([PaymentAmountMismatch::class]);

    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user);

    postStripeWebhook(stripeWebhookEvent('payment_intent.succeeded', stripePaymentIntent($order, [
        // Off by one cent: the processor reports less than the order total.
        'amount_received' => 1998,
    ])))->assertSuccessful();

    $fresh = $order->fresh();

    expect($fresh->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($fresh->payment_status)->toBe(PaymentStatus::Pending)
        ->and($fresh->meta['payment_flag'] ?? null)->toBe('amount_mismatch');

    Event::assertDispatched(
        PaymentAmountMismatch::class,
        fn (PaymentAmountMismatch $event): bool => $event->order->is($order)
    );
});

it('flags a currency mismatch without transitioning the order', function () {
    Event::fake([PaymentAmountMismatch::class]);

    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user);

    postStripeWebhook(stripeWebhookEvent('payment_intent.succeeded', stripePaymentIntent($order, [
        'currency' => 'eur',
    ])))->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->fresh()->meta['payment_flag'] ?? null)->toBe('amount_mismatch');

    Event::assertDispatched(PaymentAmountMismatch::class);
});

it('never resurrects an expired order and flags the late payment instead', function () {
    Event::fake([LatePaymentReceived::class]);

    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user, [
        'status' => OrderStatus::Expired,
        'expires_at' => now()->subHour(),
    ]);

    postStripeWebhook(stripeWebhookEvent('payment_intent.succeeded', stripePaymentIntent($order)))
        ->assertSuccessful();

    $fresh = $order->fresh();

    expect($fresh->status)->toBe(OrderStatus::Expired)
        ->and($fresh->meta['payment_flag'] ?? null)->toBe('paid_after_expiry');

    Event::assertDispatched(
        LatePaymentReceived::class,
        fn (LatePaymentReceived $event): bool => $event->order->is($order)
    );
});

it('marks the payment failed but keeps the order awaiting and retryable on payment_intent.payment_failed', function () {
    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user);

    postStripeWebhook(stripeWebhookEvent('payment_intent.payment_failed', stripePaymentIntent($order, [
        'amount_received' => 0,
    ])))->assertSuccessful();

    $fresh = $order->fresh();

    // The order status does NOT change: the shopper may retry the payment.
    expect($fresh->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($fresh->payment_status)->toBe(PaymentStatus::Failed);
});

it('acks an unknown payment intent without touching any order', function () {
    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user);

    postStripeWebhook(stripeWebhookEvent('payment_intent.succeeded', [
        'id' => 'pi_unknown_999',
        'object' => 'payment_intent',
        'amount_received' => 1999,
        'currency' => 'usd',
        'metadata' => [],
    ]))->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('acks event types it does not handle', function () {
    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user);

    postStripeWebhook(stripeWebhookEvent('customer.created', stripePaymentIntent($order)))
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment);
});

it('confirms a pending refund and derives the order statuses on a signed charge.refunded', function () {
    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user, [
        'status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
    ]);

    // The synchronous path recorded the refund but died before the gateway
    // answer came back; the webhook confirmation completes it.
    $refund = SCRefund::factory()->create([
        'order_id' => $order->getKey(),
        'gateway' => 'stripe',
        'gateway_refund_id' => 're_test_1',
        'amount_cents' => 1999,
        'currency' => 'USD',
        'status' => RefundStatus::Pending,
    ]);

    postStripeWebhook(stripeWebhookEvent('charge.refunded', [
        'id' => 'ch_test_1',
        'object' => 'charge',
        'payment_intent' => $order->transaction_id,
        'amount_refunded' => 1999,
        'refunds' => ['object' => 'list', 'data' => [
            ['id' => 're_test_1', 'object' => 'refund', 'status' => 'succeeded'],
        ]],
    ], 'evt_charge_refunded_1'))->assertSuccessful();

    expect($refund->fresh()->status)->toBe(RefundStatus::Succeeded)
        // The webhook path NEVER restocks; only the synchronous path does.
        ->and($refund->fresh()->restocked)->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(DB::table(stripeWebhookEventsTable())->where('event_id', 'evt_charge_refunded_1')->count())->toBe(1);
});

it('marks the matching refund failed on a signed refund.failed', function () {
    Event::fake([RefundFailed::class]);

    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user, [
        'status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
    ]);

    $refund = SCRefund::factory()->create([
        'order_id' => $order->getKey(),
        'gateway' => 'stripe',
        'gateway_refund_id' => 're_test_failed_1',
        'amount_cents' => 1999,
        'currency' => 'USD',
        'status' => RefundStatus::Pending,
    ]);

    postStripeWebhook(stripeWebhookEvent('refund.failed', [
        'id' => 're_test_failed_1',
        'object' => 'refund',
        'status' => 'failed',
        'failure_reason' => 'expired_or_canceled_card',
        'payment_intent' => $order->transaction_id,
    ], 'evt_refund_failed_1'))->assertSuccessful();

    expect($refund->fresh()->status)->toBe(RefundStatus::Failed)
        ->and($refund->fresh()->failure_reason)->toBe('expired_or_canceled_card')
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);

    Event::assertDispatched(
        RefundFailed::class,
        fn (RefundFailed $event): bool => $event->refund->is($refund)
    );
});

it('flags the order and dispatches DisputeCreated on a signed charge.dispute.created', function () {
    Event::fake([DisputeCreated::class]);

    $user = stripeWebhookUser();
    $order = stripeWebhookOrder($user, [
        'status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
    ]);

    postStripeWebhook(stripeWebhookEvent('charge.dispute.created', [
        'id' => 'dp_test_1',
        'object' => 'dispute',
        'payment_intent' => $order->transaction_id,
        'reason' => 'fraudulent',
    ], 'evt_dispute_1'))->assertSuccessful();

    expect($order->fresh()->meta['payment_flag'] ?? null)->toBe('disputed')
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(DB::table(stripeWebhookEventsTable())->where('event_id', 'evt_dispute_1')->count())->toBe(1);

    Event::assertDispatched(
        DisputeCreated::class,
        fn (DisputeCreated $event): bool => $event->order->is($order) && ($event->dispute['id'] ?? null) === 'dp_test_1'
    );
});

it('answers 404 for an unknown gateway url', function () {
    test()->call('POST', '/sc/webhooks/not-a-gateway', server: [
        'CONTENT_TYPE' => 'application/json',
    ], content: '{}')->assertStatus(404);
});

it('serves the webhook route without any middleware so no csrf or session applies', function () {
    $route = collect(Route::getRoutes())->first(
        fn ($route): bool => $route->uri() === 'sc/webhooks/{gateway}'
    );

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toBe([]);
});

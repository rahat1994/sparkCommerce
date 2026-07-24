<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Rahat1994\SparkCommerce\Enums\AdminAlertReason;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Events\DisputeCreated;
use Rahat1994\SparkCommerce\Events\FreeOrderPlaced;
use Rahat1994\SparkCommerce\Events\OrderAutoRefunded;
use Rahat1994\SparkCommerce\Events\PaymentAmountMismatch;
use Rahat1994\SparkCommerce\Events\PaymentNeedsReconciliation;
use Rahat1994\SparkCommerce\Events\ProductBackordered;
use Rahat1994\SparkCommerce\Events\RefundFailed;
use Rahat1994\SparkCommerce\Events\WebhookSignatureFailing;
use Rahat1994\SparkCommerce\Exceptions\RefundGatewayFailed;
use Rahat1994\SparkCommerce\Jobs\HandlePaymentIntentSucceeded;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCProduct;
use Rahat1994\SparkCommerce\Models\SCRefund;
use Rahat1994\SparkCommerce\Notifications\AdminPaymentAlert;
use Rahat1994\SparkCommerce\Notifications\BackorderNotification;
use Rahat1994\SparkCommerce\Notifications\NewOrderReceived;
use Rahat1994\SparkCommerce\Notifications\OrderConfirmation;
use Rahat1994\SparkCommerce\Notifications\OrderRefunded;
use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;
use Rahat1994\SparkCommerce\Services\OrderTransitionService;
use Rahat1994\SparkCommerce\Services\RefundService;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;

const ADMIN_MAILBOX = 'store-admin@example.com';

function notifiableCustomer(): User
{
    return User::create([
        'name' => 'Shopper',
        'email' => 'shopper@example.com',
        'password' => bcrypt('password'),
    ]);
}

/**
 * An awaiting_payment order on the fake gateway, the way checkout leaves
 * it (2000 cents unless overridden).
 *
 * @param  array<string, mixed>  $attributes
 */
function notifiableOrder(?User $user, array $attributes = []): SCOrder
{
    return SCOrder::factory()->awaitingPayment()->create([
        'user_id' => $user?->id,
        'currency' => 'USD',
        'total_amount_cents' => 2000,
        'payment_gateway' => 'fake',
        'transaction_id' => 'fake_pi_notify',
        ...$attributes,
    ]);
}

function transitionOrder(SCOrder $order, OrderStatus $to, ?PaymentStatus $paymentStatus = null): SCOrder
{
    return app(OrderTransitionService::class)->transition(
        $order,
        $to,
        $paymentStatus !== null ? ['payment_status' => $paymentStatus] : [],
    );
}

function wasRoutedToAdmin(object $notifiable): bool
{
    return $notifiable instanceof AnonymousNotifiable
        && ($notifiable->routes['mail'] ?? null) === ADMIN_MAILBOX;
}

beforeEach(function () {
    config()->set('sparkcommerce.admin_email', ADMIN_MAILBOX);
});

it('queues exactly two notifications when an order transitions to paid', function () {
    Notification::fake();

    $user = notifiableCustomer();
    $order = notifiableOrder($user);

    transitionOrder($order, OrderStatus::Paid, PaymentStatus::Paid);

    Notification::assertSentTo(
        $user,
        OrderConfirmation::class,
        fn (OrderConfirmation $notification): bool => $notification->order->is($order)
    );

    Notification::assertSentOnDemand(
        NewOrderReceived::class,
        fn (NewOrderReceived $notification, array $channels, object $notifiable): bool => wasRoutedToAdmin($notifiable)
            && $notification->order->is($order)
    );

    Notification::assertCount(2);
});

it('queues nothing on order creation, expiry, or cancellation of unpaid orders', function () {
    Notification::fake();

    $user = notifiableCustomer();

    // Checkout-equivalent: creating the awaiting_payment order is not a
    // transition and must never mail anyone.
    $created = notifiableOrder($user);

    transitionOrder(notifiableOrder($user), OrderStatus::Expired);
    transitionOrder(notifiableOrder($user), OrderStatus::Cancelled);

    Notification::assertNothingSent();
});

it('sends only the admin mail when a paid order has no user', function () {
    Notification::fake();

    $order = notifiableOrder(null);

    transitionOrder($order, OrderStatus::Paid, PaymentStatus::Paid);

    Notification::assertSentOnDemand(NewOrderReceived::class);
    Notification::assertCount(1);
});

it('sends only the customer mail when no admin email is configured', function () {
    config()->set('sparkcommerce.admin_email', null);
    Notification::fake();

    $user = notifiableCustomer();
    $order = notifiableOrder($user);

    transitionOrder($order, OrderStatus::Paid, PaymentStatus::Paid);

    Notification::assertSentTo($user, OrderConfirmation::class);
    Notification::assertCount(1);
});

it('queues an order refunded mail carrying the amount on a partial refund', function () {
    $user = notifiableCustomer();
    $order = notifiableOrder($user, ['status' => OrderStatus::Paid, 'payment_status' => PaymentStatus::Paid]);

    Notification::fake();

    app(RefundService::class)->refund($order, 500, initiatedBy: null, restock: false);

    Notification::assertSentTo(
        $user,
        OrderRefunded::class,
        fn (OrderRefunded $notification): bool => (int) $notification->refund->getRawOriginal('amount_cents') === 500
            && $notification->order->is($order)
    );

    Notification::assertCount(1);
});

it('queues only the order refunded mail on a full refund', function () {
    $user = notifiableCustomer();
    $order = notifiableOrder($user, ['status' => OrderStatus::Paid, 'payment_status' => PaymentStatus::Paid]);

    Notification::fake();

    app(RefundService::class)->refund($order, 2000, initiatedBy: null, restock: false);

    Notification::assertSentTo(
        $user,
        OrderRefunded::class,
        fn (OrderRefunded $notification): bool => (int) $notification->refund->getRawOriginal('amount_cents') === 2000
    );

    // The Paid -> Refunded transition must not trigger any paid-order mail.
    Notification::assertCount(1);
});

it('queues the order refunded mail exactly once when a pending refund is confirmed by webhook', function () {
    $user = notifiableCustomer();
    $order = notifiableOrder($user, ['status' => OrderStatus::Paid, 'payment_status' => PaymentStatus::Paid]);
    $refund = SCRefund::factory()->create(['order_id' => $order->id, 'amount_cents' => 700]);

    Notification::fake();

    app(RefundService::class)->confirmFromWebhook($refund, 'fake_re_hook');
    // Redelivery of the same webhook is a no-op and must not mail again.
    app(RefundService::class)->confirmFromWebhook($refund->fresh(), 'fake_re_hook');

    Notification::assertSentToTimes($user, OrderRefunded::class, 1);
    Notification::assertCount(1);
});

it('queues a refund_failed admin alert when the gateway refuses a refund', function () {
    $user = notifiableCustomer();
    $order = notifiableOrder($user, ['status' => OrderStatus::Paid, 'payment_status' => PaymentStatus::Paid]);

    Notification::fake();
    FakeGateway::failRefunds('scripted gateway refusal');

    expect(fn () => app(RefundService::class)->refund($order, 500, initiatedBy: null, restock: false))
        ->toThrow(RefundGatewayFailed::class);

    Notification::assertSentOnDemand(
        AdminPaymentAlert::class,
        fn (AdminPaymentAlert $alert, array $channels, object $notifiable): bool => wasRoutedToAdmin($notifiable)
            && $alert->reason === AdminAlertReason::RefundFailed
    );

    Notification::assertNotSentTo($user, OrderRefunded::class);
    Notification::assertCount(1);
});

it('queues a backorder notification only when the backordered cart belongs to a user', function () {
    Notification::fake();

    $user = notifiableCustomer();
    $product = SCProduct::factory()->managedStock(2)->create();

    event(new ProductBackordered($product, $user, 5));

    Notification::assertSentTo(
        $user,
        BackorderNotification::class,
        fn (BackorderNotification $notification): bool => $notification->product->is($product)
            && $notification->quantity === 5
    );

    Notification::assertCount(1);
});

it('queues nothing for a backorder without a user', function () {
    Notification::fake();

    $product = SCProduct::factory()->managedStock(2)->create();

    event(new ProductBackordered($product, null, 5));

    Notification::assertNothingSent();
});

it('queues one admin alert with the matching reason per operational event', function (object $event, AdminAlertReason $reason) {
    Notification::fake();

    event($event);

    Notification::assertSentOnDemand(
        AdminPaymentAlert::class,
        fn (AdminPaymentAlert $alert, array $channels, object $notifiable): bool => wasRoutedToAdmin($notifiable)
            && $alert->reason === $reason
    );

    Notification::assertCount(1);
})->with([
    'amount mismatch' => [
        fn (): object => new PaymentAmountMismatch(notifiableOrder(null), 999, 'USD'),
        AdminAlertReason::AmountMismatch,
    ],
    'late payment auto-refunded' => [
        fn (): object => new OrderAutoRefunded(
            $order = notifiableOrder(null, ['status' => OrderStatus::Expired]),
            SCRefund::factory()->succeeded()->create(['order_id' => $order->id]),
        ),
        AdminAlertReason::LatePaymentRefunded,
    ],
    'dispute created' => [
        fn (): object => new DisputeCreated(notifiableOrder(null, ['status' => OrderStatus::Paid]), ['id' => 'dp_1']),
        AdminAlertReason::DisputeCreated,
    ],
    'refund failed' => [
        fn (): object => new RefundFailed(SCRefund::factory()->failed()->create([
            'order_id' => notifiableOrder(null, ['status' => OrderStatus::Paid])->id,
        ])),
        AdminAlertReason::RefundFailed,
    ],
    'webhook signature failing' => [
        fn (): object => new WebhookSignatureFailing('fake', 5),
        AdminAlertReason::WebhookSignatureFailing,
    ],
    'needs reconciliation' => [
        fn (): object => new PaymentNeedsReconciliation(notifiableOrder(null, ['status' => OrderStatus::Expired])),
        AdminAlertReason::NeedsReconciliation,
    ],
]);

it('mails a free order exactly like a paid order plus one free-order admin alert', function () {
    Notification::fake();

    $user = notifiableCustomer();
    $order = notifiableOrder($user, ['total_amount_cents' => 0]);

    // What checkout does for a zero-total order: straight to Paid, then
    // the FreeOrderPlaced seam.
    transitionOrder($order, OrderStatus::Paid, PaymentStatus::Paid);
    event(new FreeOrderPlaced($order->fresh()));

    Notification::assertSentToTimes($user, OrderConfirmation::class, 1);
    Notification::assertSentOnDemandTimes(NewOrderReceived::class, 1);
    Notification::assertSentOnDemand(
        AdminPaymentAlert::class,
        fn (AdminPaymentAlert $alert): bool => $alert->reason === AdminAlertReason::FreeOrderPlaced
    );
    Notification::assertSentOnDemandTimes(AdminPaymentAlert::class, 1);
    Notification::assertCount(3);
});

it('alerts the admin when a payment webhook job fails after its retries', function () {
    Notification::fake();

    // The queue worker gave up after the job exhausted its retries.
    (new HandlePaymentIntentSucceeded('stripe', ['id' => 'evt_boom']))
        ->failed(new RuntimeException('queue worker gave up'));

    Notification::assertSentOnDemand(
        AdminPaymentAlert::class,
        fn (AdminPaymentAlert $alert, array $channels, object $notifiable): bool => wasRoutedToAdmin($notifiable)
            && $alert->reason === AdminAlertReason::JobFailed
    );

    Notification::assertCount(1);
});

it('renders every mailable with a non-empty subject and body', function () {
    $user = notifiableCustomer();
    $order = notifiableOrder($user, ['status' => OrderStatus::Paid, 'payment_status' => PaymentStatus::Paid]);
    $refund = SCRefund::factory()->succeeded()->create(['order_id' => $order->id, 'amount_cents' => 500]);
    $product = SCProduct::factory()->create();

    $notifications = [
        new OrderConfirmation($order),
        new NewOrderReceived($order),
        new OrderRefunded($order, $refund),
        new BackorderNotification($product, 3),
        ...array_map(
            fn (AdminAlertReason $reason): AdminPaymentAlert => new AdminPaymentAlert($reason, [
                'order_number' => $order->order_number,
                'amount' => '5.00',
                'currency' => 'USD',
                'expected' => '20.00',
                'failure_reason' => 'card_network_declined',
                'gateway' => 'fake',
                'failures' => 5,
            ]),
            AdminAlertReason::cases(),
        ),
    ];

    foreach ($notifications as $notification) {
        $mailMessage = $notification->toMail($user);
        $body = (string) $mailMessage->render();

        expect($mailMessage->subject)->toBeString()->not->toBe('')
            ->and($body)->not->toBe('');
    }

    // The customer refund mail must state the refunded amount.
    expect((string) (new OrderRefunded($order, $refund))->toMail($user)->render())->toContain('5.00');
});

it('queues every notification after commit', function () {
    $user = notifiableCustomer();
    $order = notifiableOrder($user, ['status' => OrderStatus::Paid, 'payment_status' => PaymentStatus::Paid]);
    $refund = SCRefund::factory()->succeeded()->create(['order_id' => $order->id]);
    $product = SCProduct::factory()->create();

    $notifications = [
        new OrderConfirmation($order),
        new NewOrderReceived($order),
        new OrderRefunded($order, $refund),
        new BackorderNotification($product, 3),
        new AdminPaymentAlert(AdminAlertReason::AmountMismatch, []),
    ];

    foreach ($notifications as $notification) {
        expect($notification)->toBeInstanceOf(ShouldQueue::class)
            ->and($notification->afterCommit)->toBeTrue()
            ->and($notification->via($user))->toBe(['mail']);
    }
});

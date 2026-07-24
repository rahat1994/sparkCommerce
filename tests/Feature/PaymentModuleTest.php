<?php

use Binafy\LaravelCart\Models\Cart;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Events\PaymentNeedsReconciliation;
use Rahat1994\SparkCommerce\Jobs\HandlePaymentIntentSucceeded;
use Rahat1994\SparkCommerce\Jobs\PrunePaymentEvents;
use Rahat1994\SparkCommerce\Jobs\ReconcileStuckPayments;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\Contracts\PaymentGateway;
use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;
use Rahat1994\SparkCommerce\Payments\Drivers\StripeGateway;
use Rahat1994\SparkCommerce\Payments\Exceptions\PaymentCancellationRefused;
use Rahat1994\SparkCommerce\Payments\PaymentGatewayManager;
use Rahat1994\SparkCommerce\Payments\PaymentSession;
use Rahat1994\SparkCommerce\Payments\RefundResult;
use Rahat1994\SparkCommerce\Services\OrderTransitionService;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;
use Symfony\Component\HttpFoundation\Response;

function paymentModuleUser(): User
{
    return User::create([
        'name' => 'Payment Shopper',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('secret-password'),
    ]);
}

/**
 * An order shaped the way checkout leaves it: awaiting payment, integer
 * cents total, currency stamped, no transaction yet.
 *
 * @param  array<string, mixed>  $attributes
 */
function paymentModuleOrder(array $attributes = []): SCOrder
{
    return SCOrder::factory()->awaitingPayment()->create([
        'currency' => 'USD',
        'total_amount_cents' => 1999,
        ...$attributes,
    ]);
}

function paymentEventsTable(): string
{
    return config('sparkcommerce.table_prefix') . config('sparkcommerce.payment_events_table_name');
}

/**
 * The adopter extension used by the extendability test below: a driver the
 * base package has never heard of, registered from a service provider the
 * way an adopter package would.
 */
class PaymentModuleCustomGateway implements PaymentGateway
{
    public function createPayment(SCOrder $order): PaymentSession
    {
        return new PaymentSession(
            gateway: 'custom',
            flow: 'redirect',
            reference: 'custom_ref_' . $order->getKey(),
            clientParams: ['redirect_url' => 'https://pay.example/custom_ref_' . $order->getKey()],
        );
    }

    public function cancelPayment(SCOrder $order): void {}

    public function refund(SCOrder $order, int $amountCents, string $idempotencyKey): RefundResult
    {
        return new RefundResult(reference: 'custom_refund_' . $order->getKey(), status: 'succeeded');
    }

    public function retrievePaymentStatus(SCOrder $order): ?string
    {
        return null;
    }

    public function handleWebhook(Request $request): Response
    {
        if ($request->header('X-Custom-Signature') !== 'custom-shared-secret') {
            return response('Invalid signature.', 400);
        }

        $event = (array) json_decode($request->getContent(), true);

        if (($event['type'] ?? null) === 'payment_intent.succeeded') {
            HandlePaymentIntentSucceeded::dispatch('custom', $event);
        }

        return response()->json(['received' => true]);
    }
}

class PaymentModuleAdopterServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(PaymentGatewayManager::class)
            ->extend('custom', fn (): PaymentGateway => new PaymentModuleCustomGateway);
    }
}

it('binds the manager as a singleton under the alias and resolves the fake driver in tests', function () {
    $manager = $this->app->make('sparkcommerce.payments');

    expect($manager)->toBeInstanceOf(PaymentGatewayManager::class)
        ->and($this->app->make(PaymentGatewayManager::class))->toBe($manager)
        // The test environment pins the default driver to 'fake'.
        ->and($manager->driver())->toBeInstanceOf(FakeGateway::class);
});

it('resolves the shipped stripe driver by name', function () {
    expect($this->app->make('sparkcommerce.payments')->driver('stripe'))
        ->toBeInstanceOf(StripeGateway::class);
});

it('creates idempotent fake payment sessions per order and total', function () {
    $order = paymentModuleOrder();

    $manager = $this->app->make(PaymentGatewayManager::class);

    $first = $manager->driver('fake')->createPayment($order);
    $second = $manager->driver('fake')->createPayment($order);

    expect($first)->toBeInstanceOf(PaymentSession::class)
        ->and($first->gateway)->toBe('fake')
        ->and($first->flow)->toBe('client_confirm')
        ->and($first->reference)->toBe('fake_pi_' . $order->getKey())
        ->and($first->clientParams['client_secret'] ?? null)->toBeString()
        // Same order, same total: the exact same session comes back.
        ->and($second->reference)->toBe($first->reference)
        ->and($second->clientParams)->toBe($first->clientParams)
        ->and(FakeGateway::calls('createPayment'))->toHaveCount(2);
});

it('lets tests script the fake gateway to refuse cancellation', function () {
    $order = paymentModuleOrder(['transaction_id' => 'fake_pi_1', 'payment_gateway' => 'fake']);

    $driver = $this->app->make(PaymentGatewayManager::class)->driver('fake');

    $driver->cancelPayment($order);

    expect(FakeGateway::calls('cancelPayment'))->toHaveCount(1);

    FakeGateway::refuseCancellation();

    $driver->cancelPayment($order);
})->throws(PaymentCancellationRefused::class);

it('records fake refunds and returns a refund result', function () {
    $order = paymentModuleOrder(['transaction_id' => 'fake_pi_1', 'payment_gateway' => 'fake']);

    $result = $this->app->make(PaymentGatewayManager::class)
        ->driver('fake')
        ->refund($order, 500, 'refund-key-1');

    expect($result)->toBeInstanceOf(RefundResult::class)
        ->and($result->status)->toBe('succeeded')
        ->and(FakeGateway::calls('refund'))->toHaveCount(1)
        ->and(FakeGateway::calls('refund')[0]['amount_cents'])->toBe(500)
        ->and(FakeGateway::calls('refund')[0]['idempotency_key'])->toBe('refund-key-1');
});

it('lets tests script the fake payment status per reference', function () {
    $order = paymentModuleOrder(['transaction_id' => 'fake_pi_9', 'payment_gateway' => 'fake']);

    $driver = $this->app->make(PaymentGatewayManager::class)->driver('fake');

    expect($driver->retrievePaymentStatus($order))->toBeNull();

    FakeGateway::scriptStatus('fake_pi_9', 'succeeded');

    expect($driver->retrievePaymentStatus($order))->toBe('succeeded');
});

it('completes an adopter-registered custom driver from payment to paid through the generic webhook route', function () {
    $this->app->register(PaymentModuleAdopterServiceProvider::class);

    $user = paymentModuleUser();
    $order = paymentModuleOrder(['user_id' => $user->id]);

    // The storefront would call the manager exactly like this at checkout.
    $session = $this->app->make(PaymentGatewayManager::class)
        ->driver('custom')
        ->createPayment($order);

    expect($session->gateway)->toBe('custom')
        ->and($session->flow)->toBe('redirect');

    $order->transaction_id = $session->reference;
    $order->payment_gateway = $session->gateway;
    $order->save();

    // The processor notifies the SAME generic route every gateway shares.
    $payload = json_encode([
        'id' => 'evt_custom_1',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => $session->reference,
                'amount_received' => 1999,
                'currency' => 'usd',
                'metadata' => ['order_id' => (string) $order->getKey()],
            ],
        ],
    ]);

    $this->call('POST', '/sc/webhooks/custom', server: [
        'HTTP_X_CUSTOM_SIGNATURE' => 'custom-shared-secret',
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload)->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(DB::table(paymentEventsTable())->where('gateway', 'custom')->where('event_id', 'evt_custom_1')->count())->toBe(1);
});

it('denies the custom webhook without the shared secret', function () {
    $this->app->register(PaymentModuleAdopterServiceProvider::class);

    $order = paymentModuleOrder(['transaction_id' => 'custom_ref_1', 'payment_gateway' => 'custom']);

    $this->call('POST', '/sc/webhooks/custom', server: [
        'CONTENT_TYPE' => 'application/json',
    ], content: json_encode(['id' => 'evt_custom_2', 'type' => 'payment_intent.succeeded']))
        ->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(DB::table(paymentEventsTable())->count())->toBe(0);
});

it('flags awaiting orders whose gateway already succeeded during reconciliation', function () {
    Event::fake([PaymentNeedsReconciliation::class]);

    $succeeded = paymentModuleOrder([
        'transaction_id' => 'fake_pi_done',
        'payment_gateway' => 'fake',
        'expires_at' => now()->subMinutes(10),
    ]);

    $stillPending = paymentModuleOrder([
        'transaction_id' => 'fake_pi_pending',
        'payment_gateway' => 'fake',
        'expires_at' => now()->subMinutes(10),
    ]);

    FakeGateway::scriptStatus('fake_pi_done', 'succeeded');
    FakeGateway::scriptStatus('fake_pi_pending', 'requires_payment_method');

    (new ReconcileStuckPayments)->handle($this->app->make(PaymentGatewayManager::class));

    expect($succeeded->fresh()->meta['payment_flag'] ?? null)->toBe('needs_reconciliation')
        // The order is NOT transitioned; only flagged for the admin seam.
        ->and($succeeded->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($stillPending->fresh()->meta['payment_flag'] ?? null)->toBeNull();

    Event::assertDispatched(
        PaymentNeedsReconciliation::class,
        fn (PaymentNeedsReconciliation $event): bool => $event->order->is($succeeded)
    );

    // A second sweep must not re-flag or re-dispatch.
    (new ReconcileStuckPayments)->handle($this->app->make(PaymentGatewayManager::class));

    Event::assertDispatchedTimes(PaymentNeedsReconciliation::class, 1);
});

it('registers the reconciliation and claim-prune tasks on the scheduler', function () {
    $schedule = $this->app->make(Schedule::class);

    $events = collect($schedule->events());

    $reconcile = $events->first(
        fn ($event): bool => str_contains((string) $event->description, ReconcileStuckPayments::class)
    );

    $prune = $events->first(
        fn ($event): bool => str_contains((string) $event->description, PrunePaymentEvents::class)
    );

    expect($reconcile)->not->toBeNull()
        ->and($reconcile->expression)->toBe('*/30 * * * *')
        ->and($prune)->not->toBeNull()
        ->and($prune->expression)->toBe('0 0 * * *');
});

it('prunes webhook claim rows older than thirty days and keeps recent ones', function () {
    DB::table(paymentEventsTable())->insert([
        ['gateway' => 'stripe', 'event_id' => 'evt_old', 'created_at' => now()->subDays(31)],
        ['gateway' => 'stripe', 'event_id' => 'evt_recent', 'created_at' => now()->subDays(29)],
    ]);

    (new PrunePaymentEvents)->handle();

    expect(DB::table(paymentEventsTable())->pluck('event_id')->all())->toBe(['evt_recent']);
});

it('deletes the shopper cart only when the paid webhook lands', function () {
    $user = paymentModuleUser();
    $order = paymentModuleOrder([
        'user_id' => $user->id,
        'transaction_id' => 'fake_pi_cart',
        'payment_gateway' => 'fake',
    ]);

    // The cart exactly as checkout reads it: the user's Cart row.
    Cart::query()->firstOrCreate(['user_id' => $user->id]);

    (new HandlePaymentIntentSucceeded('fake', [
        'id' => 'evt_fake_cart',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'fake_pi_cart',
                'amount_received' => 1999,
                'currency' => 'usd',
            ],
        ],
    ]))->handle($this->app->make(OrderTransitionService::class));

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(Cart::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

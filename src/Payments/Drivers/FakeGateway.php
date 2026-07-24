<?php

namespace Rahat1994\SparkCommerce\Payments\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Rahat1994\SparkCommerce\Jobs\HandleChargeRefunded;
use Rahat1994\SparkCommerce\Jobs\HandleDisputeCreated;
use Rahat1994\SparkCommerce\Jobs\HandlePaymentIntentFailed;
use Rahat1994\SparkCommerce\Jobs\HandlePaymentIntentSucceeded;
use Rahat1994\SparkCommerce\Jobs\HandleRefundFailed;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\Contracts\PaymentGateway;
use Rahat1994\SparkCommerce\Payments\Exceptions\PaymentCancellationRefused;
use Rahat1994\SparkCommerce\Payments\PaymentSession;
use Rahat1994\SparkCommerce\Payments\RefundResult;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * In-memory test double mirroring the real gateway semantics: idempotent
 * payment sessions per order + total, scriptable cancellation refusal and
 * payment status, and call recording for assertions. Reset the static
 * state between tests with {@see reset()}.
 */
class FakeGateway implements PaymentGateway
{
    /** @var array<string, PaymentSession> keyed by idempotency key */
    protected static array $sessions = [];

    /** @var array<int, array<string, mixed>> */
    protected static array $calls = [];

    protected static bool $refuseCancellation = false;

    /** @var array<string, string|null> payment status keyed by reference */
    protected static array $scriptedStatuses = [];

    /** Scripted refusal reason for refund calls; null = refunds succeed. */
    protected static ?string $refundFailureReason = null;

    public function createPayment(SCOrder $order): PaymentSession
    {
        $idempotencyKey = static::idempotencyKeyFor($order);

        static::$calls[] = [
            'method' => 'createPayment',
            'order_id' => $order->getKey(),
            'idempotency_key' => $idempotencyKey,
        ];

        // Idempotent per order + total, exactly like Stripe's idempotency
        // keys: the same key returns the SAME session.
        return static::$sessions[$idempotencyKey] ??= new PaymentSession(
            gateway: 'fake',
            flow: 'client_confirm',
            reference: 'fake_pi_' . $order->getKey(),
            clientParams: ['client_secret' => 'fake_secret_' . $order->getKey() . '_' . Str::random(8)],
        );
    }

    public function cancelPayment(SCOrder $order): void
    {
        static::$calls[] = [
            'method' => 'cancelPayment',
            'order_id' => $order->getKey(),
            'reference' => $order->transaction_id,
        ];

        if (static::$refuseCancellation) {
            throw PaymentCancellationRefused::forOrder($order->getKey());
        }
    }

    public function refund(SCOrder $order, int $amountCents, string $idempotencyKey): RefundResult
    {
        static::$calls[] = [
            'method' => 'refund',
            'order_id' => $order->getKey(),
            'reference' => $order->transaction_id,
            'amount_cents' => $amountCents,
            'idempotency_key' => $idempotencyKey,
        ];

        if (static::$refundFailureReason !== null) {
            throw new RuntimeException(static::$refundFailureReason);
        }

        return new RefundResult(
            reference: 'fake_re_' . $order->getKey(),
            status: 'succeeded',
        );
    }

    public function retrievePaymentStatus(SCOrder $order): ?string
    {
        static::$calls[] = [
            'method' => 'retrievePaymentStatus',
            'order_id' => $order->getKey(),
            'reference' => $order->transaction_id,
        ];

        return static::$scriptedStatuses[$order->transaction_id] ?? null;
    }

    /**
     * Default-deny stand-in for real signature verification: the request
     * must carry the shared test secret in the X-Fake-Webhook-Secret
     * header. Verified events map to the same jobs the Stripe driver
     * dispatches, so the generic webhook route can be exercised end to end.
     */
    public function handleWebhook(Request $request): Response
    {
        $sharedSecret = (string) config('sparkcommerce.payments.gateways.fake.webhook_secret', '');

        if ($sharedSecret === '' || ! hash_equals($sharedSecret, (string) $request->header('X-Fake-Webhook-Secret', ''))) {
            return response('Invalid signature.', 400);
        }

        $event = (array) json_decode((string) $request->getContent(), true);

        match ($event['type'] ?? null) {
            'payment_intent.succeeded' => HandlePaymentIntentSucceeded::dispatch('fake', $event),
            'payment_intent.payment_failed' => HandlePaymentIntentFailed::dispatch('fake', $event),
            'charge.refunded' => HandleChargeRefunded::dispatch('fake', $event),
            'refund.failed' => HandleRefundFailed::dispatch('fake', $event),
            'charge.dispute.created' => HandleDisputeCreated::dispatch('fake', $event),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    public static function reset(): void
    {
        static::$sessions = [];
        static::$calls = [];
        static::$refuseCancellation = false;
        static::$scriptedStatuses = [];
        static::$refundFailureReason = null;
    }

    /**
     * Recorded calls, optionally filtered by method name.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function calls(?string $method = null): array
    {
        if ($method === null) {
            return static::$calls;
        }

        return array_values(array_filter(
            static::$calls,
            fn (array $call): bool => $call['method'] === $method
        ));
    }

    public static function refuseCancellation(bool $refuse = true): void
    {
        static::$refuseCancellation = $refuse;
    }

    public static function scriptStatus(string $reference, ?string $status): void
    {
        static::$scriptedStatuses[$reference] = $status;
    }

    /**
     * Script every subsequent refund call to throw with the given reason
     * (the call is still recorded first). Pass null to succeed again.
     */
    public static function failRefunds(?string $reason = 'refund_failed'): void
    {
        static::$refundFailureReason = $reason;
    }

    protected static function idempotencyKeyFor(SCOrder $order): string
    {
        return sprintf('order-%s-%d', $order->getKey(), (int) $order->getRawOriginal('total_amount_cents'));
    }
}

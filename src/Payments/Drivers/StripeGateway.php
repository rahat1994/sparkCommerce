<?php

namespace Rahat1994\SparkCommerce\Payments\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Rahat1994\SparkCommerce\Events\WebhookSignatureFailing;
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
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Util\ApiVersion;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * The shipped Stripe driver: PaymentIntents on the PLATFORM account.
 * Deliberately no transfer_data / application_fee — Connect routing is a
 * later release.
 *
 * Config (env-driven): `sparkcommerce.payments.gateways.stripe.secret_key`
 * and `.webhook_secret`.
 */
class StripeGateway implements PaymentGateway
{
    protected StripeClient $client;

    /**
     * @param  array{secret_key?: string|null, webhook_secret?: string|null}  $config
     */
    public function __construct(protected array $config)
    {
        $options = [
            // Pin the API version to the one this stripe-php release is
            // built against (ApiVersion::CURRENT), so a Stripe account-level
            // version bump can never change response shapes under us.
            'stripe_version' => ApiVersion::CURRENT,
        ];

        if (! empty($config['secret_key'])) {
            $options['api_key'] = $config['secret_key'];
        }

        $this->client = new StripeClient($options);
    }

    public function createPayment(SCOrder $order): PaymentSession
    {
        $amountCents = (int) $order->getRawOriginal('total_amount_cents');

        // The idempotency key makes an unchanged re-checkout return the
        // SAME PaymentIntent instead of opening a second charge attempt.
        // Stripe keeps keys for 24h; the order TTL is far shorter, so a
        // reused key can never target a vanished order.
        $intent = $this->client->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => strtolower((string) $order->currency),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'order_id' => (string) $order->getKey(),
                'tracking_number' => (string) $order->tracking_number,
            ],
        ], [
            'idempotency_key' => sprintf('order-%s-%d', $order->getKey(), $amountCents),
        ]);

        return new PaymentSession(
            gateway: 'stripe',
            flow: 'client_confirm',
            reference: $intent->id,
            clientParams: ['client_secret' => (string) $intent->client_secret],
        );
    }

    public function cancelPayment(SCOrder $order): void
    {
        if ($order->transaction_id === null) {
            return;
        }

        try {
            $this->client->paymentIntents->cancel($order->transaction_id);
        } catch (ApiErrorException $exception) {
            // `payment_intent_unexpected_state` covers every "cannot cancel
            // from here" case, including an intent that already succeeded:
            // the caller must keep the order instead of orphaning a charge.
            if ($exception->getStripeCode() === 'payment_intent_unexpected_state') {
                throw PaymentCancellationRefused::forOrder($order->getKey(), $exception);
            }

            throw $exception;
        }
    }

    public function refund(SCOrder $order, int $amountCents, string $idempotencyKey): RefundResult
    {
        $refund = $this->client->refunds->create([
            'payment_intent' => (string) $order->transaction_id,
            'amount' => $amountCents,
        ], [
            'idempotency_key' => $idempotencyKey,
        ]);

        return new RefundResult(
            reference: (string) $refund->id,
            status: (string) $refund->status,
        );
    }

    public function retrievePaymentStatus(SCOrder $order): ?string
    {
        if ($order->transaction_id === null) {
            return null;
        }

        try {
            $intent = $this->client->paymentIntents->retrieve($order->transaction_id);
        } catch (ApiErrorException) {
            return null;
        }

        return match ($intent->status) {
            'succeeded' => 'succeeded',
            'processing' => 'processing',
            'canceled' => 'canceled',
            'requires_payment_method' => 'requires_payment_method',
            default => null,
        };
    }

    /**
     * Real HMAC verification via Stripe's own constructEvent (the
     * `Stripe-Signature` header: t=...,v1=HMAC-SHA256("{t}.{payload}")).
     * Anything unverifiable answers 400 BEFORE any dispatch. Verified
     * payment events are queued; everything else is acked and ignored.
     */
    public function handleWebhook(Request $request): Response
    {
        $webhookSecret = (string) ($this->config['webhook_secret'] ?? '');

        // Default-deny: without a configured secret nothing can be
        // verified, so nothing is accepted.
        if ($webhookSecret === '') {
            return response('Webhook secret is not configured.', 400);
        }

        $payload = (string) $request->getContent();

        try {
            $event = Webhook::constructEvent(
                $payload,
                (string) $request->header('Stripe-Signature', ''),
                $webhookSecret,
            );
        } catch (SignatureVerificationException | UnexpectedValueException) {
            $this->recordSignatureFailure();

            return response('Invalid payload.', 400);
        }

        /** @var array<string, mixed> $eventPayload */
        $eventPayload = (array) json_decode($payload, true);

        match ($event->type) {
            'payment_intent.succeeded' => HandlePaymentIntentSucceeded::dispatch('stripe', $eventPayload),
            'payment_intent.payment_failed' => HandlePaymentIntentFailed::dispatch('stripe', $eventPayload),
            'charge.refunded' => HandleChargeRefunded::dispatch('stripe', $eventPayload),
            'refund.failed' => HandleRefundFailed::dispatch('stripe', $eventPayload),
            'charge.dispute.created' => HandleDisputeCreated::dispatch('stripe', $eventPayload),
            default => null,
        };

        return response()->json(['received' => true]);
    }

    /**
     * Count signature failures per clock hour in the cache; at five or more
     * within the hour, dispatch {@see WebhookSignatureFailing} ONCE (the
     * U14 admin-alert seam) — a sustained stream of bad signatures usually
     * means a rotated/misconfigured webhook secret, i.e. silently dropped
     * payment notifications.
     */
    protected function recordSignatureFailure(): void
    {
        $window = now()->format('YmdH');
        $key = "sparkcommerce:webhook_signature_failures:stripe:{$window}";

        Cache::add($key, 0, now()->addHour());

        $failures = (int) Cache::increment($key);

        if ($failures >= 5 && Cache::add("{$key}:alerted", true, now()->addHour())) {
            event(new WebhookSignatureFailing('stripe', $failures));
        }
    }
}

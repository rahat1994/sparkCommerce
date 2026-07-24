<?php

namespace Rahat1994\SparkCommerce\Payments\Contracts;

use Illuminate\Http\Request;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\Exceptions\PaymentCancellationRefused;
use Rahat1994\SparkCommerce\Payments\PaymentSession;
use Rahat1994\SparkCommerce\Payments\RefundResult;
use Symfony\Component\HttpFoundation\Response;

/**
 * A payment gateway driver resolved through the PaymentGatewayManager.
 *
 * Adopters register their own drivers from a service provider:
 * `app('sparkcommerce.payments')->extend('name', fn () => new MyGateway)`.
 */
interface PaymentGateway
{
    /**
     * Start (or idempotently resume) a payment for the order.
     *
     * MUST be idempotent per order + total: calling it again for the same
     * order with the same `total_amount_cents` returns the SAME session,
     * never a second charge attempt.
     */
    public function createPayment(SCOrder $order): PaymentSession;

    /**
     * Cancel the order's outstanding payment at the processor.
     *
     * @throws PaymentCancellationRefused when the processor refuses (e.g.
     *                                    the payment is in flight or has
     *                                    already succeeded); the caller must
     *                                    NOT proceed as if it were cancelled
     */
    public function cancelPayment(SCOrder $order): void;

    /**
     * Refund (part of) the order's captured payment. `$idempotencyKey`
     * guards against double refunds on retries.
     */
    public function refund(SCOrder $order, int $amountCents, string $idempotencyKey): RefundResult;

    /**
     * The processor's CURRENT status for the order's payment, normalized to
     * 'succeeded' | 'processing' | 'canceled' | 'requires_payment_method',
     * or null when unknown. Used by reconciliation.
     */
    public function retrievePaymentStatus(SCOrder $order): ?string;

    /**
     * Handle an incoming processor notification (raw body + headers).
     *
     * Default-deny: a notification that cannot be POSITIVELY verified
     * (signature, shared secret, ...) must answer 400 without dispatching
     * anything. Only a verified notification may queue payment handling.
     */
    public function handleWebhook(Request $request): Response;
}

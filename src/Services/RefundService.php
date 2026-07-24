<?php

namespace Rahat1994\SparkCommerce\Services;

use Illuminate\Support\Facades\DB;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Enums\PaymentStatus;
use Rahat1994\SparkCommerce\Enums\RefundStatus;
use Rahat1994\SparkCommerce\Events\OrderRefunded;
use Rahat1994\SparkCommerce\Events\RefundFailed;
use Rahat1994\SparkCommerce\Exceptions\RefundGatewayFailed;
use Rahat1994\SparkCommerce\Exceptions\RefundNotAllowed;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCProduct;
use Rahat1994\SparkCommerce\Models\SCRefund;
use Rahat1994\SparkCommerce\Payments\Exceptions\RefundDeclined;
use Rahat1994\SparkCommerce\Payments\PaymentGatewayManager;
use Throwable;

/**
 * Refund orchestration (U13): validate under a row lock, record the refund
 * locally, call the gateway, then derive the order's payment state from
 * the durable refund rows.
 *
 * Sync-vs-webhook authority: the SYNCHRONOUS gateway answer is
 * authoritative — Stripe refunds normally succeed inline, so a
 * non-throwing {@see refund()} marks the row succeeded right away. The
 * `charge.refunded` webhook ({@see confirmFromWebhook()}) is only an
 * idempotent confirmation: it flips a still-pending row (e.g. the process
 * died between the row commit and the gateway answer) and no-ops on an
 * already-succeeded one. `refund.failed` ({@see failFromWebhook()}) flips
 * a row to failed when the processor reverses itself asynchronously.
 *
 * Restock-once: restocking happens ONLY in the synchronous path, guarded
 * by the `restocked` flag which is flipped in the same transaction as the
 * stock increment. Webhook confirmation NEVER restocks, so a redelivered
 * `charge.refunded` can never double stock. The late-payment auto-refund
 * passes `restock: false` because the expiry transition already released
 * the reservations.
 */
class RefundService
{
    public function __construct(
        protected PaymentGatewayManager $paymentGatewayManager,
        protected OrderTransitionService $orderTransitionService,
    ) {}

    /**
     * Refund (part of) the order's captured payment.
     *
     * Over-refund guard: the requested amount plus the sum of PENDING and
     * succeeded refund rows must stay within the order total. Counting
     * pending rows makes a double submit safe — the first submission's row
     * exists (committed) before its gateway call starts, so the second
     * submission is rejected before touching the gateway.
     *
     * @param  int|null  $initiatedBy  acting admin user id; null = system
     * @param  bool  $restock  restore the order's snapshot quantities on success
     * @param  bool  $allowExpired  permit refunding a terminally-closed order
     *                              (Expired or Cancelled) — the late-payment
     *                              auto-refund path only
     *
     * @throws RefundNotAllowed when validation rejects the request
     * @throws RefundGatewayFailed when the gateway declines or errors on the refund
     */
    public function refund(SCOrder $order, int $amountCents, ?int $initiatedBy = null, bool $restock = true, bool $allowExpired = false): SCRefund
    {
        // Phase 1 — validate and record, atomically: the pending row is
        // committed BEFORE the gateway is contacted, so the refund id can
        // serve as the gateway idempotency key and the over-refund guard
        // sees in-flight attempts.
        $refund = DB::transaction(function () use ($order, $amountCents, $initiatedBy, $allowExpired): SCRefund {
            /** @var SCOrder $locked */
            $locked = SCOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            $this->assertRefundable($locked, $amountCents, $allowExpired);

            return SCRefund::query()->create([
                'order_id' => $locked->getKey(),
                'gateway' => (string) $locked->payment_gateway,
                'amount_cents' => $amountCents,
                'currency' => (string) $locked->currency,
                'status' => RefundStatus::Pending,
                'restocked' => false,
                'initiated_by' => $initiatedBy,
            ]);
        });

        // Phase 2 — the gateway call, outside any transaction (network I/O
        // must not hold a row lock). Idempotency comes from the local row id.
        try {
            $result = $this->paymentGatewayManager
                ->driver((string) $order->payment_gateway)
                ->refund($order, $amountCents, "refund-{$refund->getKey()}");
        } catch (RefundDeclined $exception) {
            // DEFINITIVE decline: the gateway positively confirmed the refund
            // did NOT happen. Only now is it safe to mark the row Failed —
            // which frees the amount in the over-refund guard and drops the
            // row out of the charge.refunded fallback (it matches Pending).
            $refund->forceFill([
                'status' => RefundStatus::Failed,
                'failure_reason' => $exception->getMessage(),
            ])->save();

            event(new RefundFailed($refund));

            throw RefundGatewayFailed::forRefund($refund->getKey(), $exception);
        } catch (Throwable $exception) {
            // AMBIGUOUS error (network / timeout, or any driver that cannot
            // prove the refund did not happen): the money MAY have moved. Do
            // NOT mark the row Failed — leaving it Pending keeps the
            // over-refund guard counting it (so a retry for the same amount
            // is rejected instead of issuing a SECOND real refund) and keeps
            // it visible to the charge.refunded webhook, which reconciles it.
            // Record the reason and rethrow.
            $refund->forceFill([
                'failure_reason' => $exception->getMessage(),
            ])->save();

            throw RefundGatewayFailed::forRefund($refund->getKey(), $exception);
        }

        // Phase 3 — record the success, restock once, derive statuses.
        return DB::transaction(function () use ($order, $refund, $result, $restock): SCRefund {
            /** @var SCOrder $locked */
            $locked = SCOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            $refund->status = RefundStatus::Succeeded;
            $refund->gateway_refund_id = $result->reference;
            $refund->save();

            if ($restock) {
                $this->restockOnce($locked, $refund);
            }

            $this->deriveOrderPaymentState($locked);

            // Refund-succeeded seam (U14): announced only once the success
            // row is durable, so the customer mail can never precede a
            // rolled-back refund.
            DB::afterCommit(function () use ($locked, $refund): void {
                event(new OrderRefunded($locked, $refund));
            });

            return $refund;
        });
    }

    /**
     * Idempotent `charge.refunded` confirmation: flip a still-pending row
     * to succeeded and derive the order's payment state. An already
     * succeeded row is a no-op. NEVER restocks — restocking is exclusively
     * the synchronous path's job, so webhook redelivery cannot double it.
     */
    public function confirmFromWebhook(SCRefund $refund, ?string $gatewayRefundId = null): void
    {
        DB::transaction(function () use ($refund, $gatewayRefundId): void {
            /** @var SCRefund $lockedRefund */
            $lockedRefund = SCRefund::query()->lockForUpdate()->findOrFail($refund->getKey());

            if ($lockedRefund->status === RefundStatus::Succeeded) {
                return;
            }

            /** @var SCOrder $lockedOrder */
            $lockedOrder = SCOrder::query()->lockForUpdate()->findOrFail($lockedRefund->order_id);

            $lockedRefund->status = RefundStatus::Succeeded;

            if ($gatewayRefundId !== null && $lockedRefund->gateway_refund_id === null) {
                $lockedRefund->gateway_refund_id = $gatewayRefundId;
            }

            $lockedRefund->save();

            $this->deriveOrderPaymentState($lockedOrder);

            // Same refund-succeeded seam as the synchronous path (U14).
            // Exactly once per row: this branch is only reached when the
            // row was still pending, and the guard above no-ops the
            // redelivered (or already synchronously handled) case.
            DB::afterCommit(function () use ($lockedOrder, $lockedRefund): void {
                event(new OrderRefunded($lockedOrder, $lockedRefund));
            });
        });
    }

    /**
     * A verified `refund.failed`: the processor reversed a refund it had
     * accepted (or one still pending). Flip the row and announce it; the
     * order's fulfillment status is never touched here.
     */
    public function failFromWebhook(SCRefund $refund, ?string $failureReason = null): void
    {
        $flipped = DB::transaction(function () use ($refund, $failureReason): bool {
            /** @var SCRefund $lockedRefund */
            $lockedRefund = SCRefund::query()->lockForUpdate()->findOrFail($refund->getKey());

            if ($lockedRefund->status === RefundStatus::Failed) {
                return false;
            }

            $lockedRefund->status = RefundStatus::Failed;
            $lockedRefund->failure_reason = $failureReason;
            $lockedRefund->save();

            return true;
        });

        if ($flipped) {
            event(new RefundFailed($refund->refresh()));
        }
    }

    /**
     * Cents still refundable for the order: the total minus every pending
     * and succeeded refund (pending rows count so in-flight refunds block
     * double submits).
     */
    public function remainingRefundableCents(SCOrder $order): int
    {
        $total = (int) $order->getRawOriginal('total_amount_cents');

        return max(0, $total - $this->refundedOrPendingCents($order));
    }

    /**
     * @throws RefundNotAllowed
     */
    protected function assertRefundable(SCOrder $locked, int $amountCents, bool $allowExpired): void
    {
        $refundableStates = [OrderStatus::Paid, OrderStatus::Processing];

        if ($allowExpired) {
            // The late-payment auto-refund path: a payment landed on an
            // already-closed order (Expired or Cancelled). The order stays
            // closed; only its payment_status ends at Refunded.
            $refundableStates[] = OrderStatus::Expired;
            $refundableStates[] = OrderStatus::Cancelled;
        }

        if (! in_array($locked->status, $refundableStates, true)) {
            throw RefundNotAllowed::orderNotRefundable($locked->status);
        }

        if ($amountCents <= 0) {
            throw RefundNotAllowed::nonPositiveAmount($amountCents);
        }

        $remaining = $this->remainingRefundableCents($locked);

        if ($amountCents > $remaining) {
            throw RefundNotAllowed::exceedsRemainingBalance($amountCents, $remaining);
        }
    }

    /**
     * Restore the order's snapshot item quantities exactly once: the
     * `restocked` flag flips in the SAME transaction as the stock
     * increments, so either both are durable or neither is.
     */
    protected function restockOnce(SCOrder $order, SCRefund $refund): void
    {
        if ($refund->restocked) {
            return;
        }

        foreach ((array) $order->items as $snapshot) {
            $itemableType = $snapshot['itemable_type'] ?? null;
            $quantity = (int) ($snapshot['quantity'] ?? 0);

            if ($quantity < 1 || ! is_string($itemableType) || ! is_a($itemableType, SCProduct::class, true)) {
                continue;
            }

            SCProduct::query()->find($snapshot['itemable_id'] ?? null)?->releaseStock($quantity);
        }

        $refund->restocked = true;
        $refund->save();
    }

    /**
     * Derive the order's payment state from the durable refund rows:
     * succeeded sum >= total means fully refunded — the order transitions
     * to Refunded through the state machine when it can (Paid/Processing);
     * a terminal order (the Expired late-payment case) only gets the
     * payment_status write. A partial sum writes PartiallyRefunded on
     * payment_status alone (fulfillment untouched), the same direct-column
     * style HandlePaymentIntentFailed uses.
     */
    protected function deriveOrderPaymentState(SCOrder $locked): void
    {
        $succeededCents = $this->succeededCents($locked);
        $totalCents = (int) $locked->getRawOriginal('total_amount_cents');

        if ($totalCents > 0 && $succeededCents >= $totalCents) {
            if ($locked->status !== null && $locked->status->canTransitionTo(OrderStatus::Refunded)) {
                $this->orderTransitionService->transition(
                    $locked,
                    OrderStatus::Refunded,
                    ['payment_status' => PaymentStatus::Refunded],
                );

                return;
            }

            $locked->payment_status = PaymentStatus::Refunded;
            $locked->save();

            return;
        }

        if ($succeededCents > 0) {
            $locked->payment_status = PaymentStatus::PartiallyRefunded;
            $locked->save();
        }
    }

    protected function succeededCents(SCOrder $order): int
    {
        return (int) SCRefund::query()
            ->where('order_id', $order->getKey())
            ->where('status', RefundStatus::Succeeded)
            ->sum('amount_cents');
    }

    protected function refundedOrPendingCents(SCOrder $order): int
    {
        return (int) SCRefund::query()
            ->where('order_id', $order->getKey())
            ->whereIn('status', [RefundStatus::Pending, RefundStatus::Succeeded])
            ->sum('amount_cents');
    }
}

<?php

namespace Rahat1994\SparkCommerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Events\RefundFailed;
use Rahat1994\SparkCommerce\Models\SCRefund;
use Rahat1994\SparkCommerce\Payments\PaymentEventClaims;
use Rahat1994\SparkCommerce\Services\RefundService;

/**
 * A verified `refund.failed`: the processor could not complete a refund it
 * had accepted. The local row (matched by the gateway refund id) is
 * flipped to failed with the processor's reason, and {@see RefundFailed}
 * announces it (U14 mail seam). The order itself is not touched here.
 */
class HandleRefundFailed implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * @param  array<string, mixed>  $event  the verified gateway event
     *                                       (`{id, type, data: {object: refund}}`)
     */
    public function __construct(
        public string $gateway,
        public array $event,
    ) {}

    public function handle(RefundService $refundService): void
    {
        $eventId = (string) Arr::get($this->event, 'id', '');

        if ($eventId === '') {
            Log::warning('Refund failed event without an id was ignored.', [
                'gateway' => $this->gateway,
            ]);

            return;
        }

        DB::transaction(function () use ($refundService, $eventId): void {
            if (! PaymentEventClaims::claim($this->gateway, $eventId)) {
                return;
            }

            /** @var array<string, mixed> $gatewayRefund */
            $gatewayRefund = (array) Arr::get($this->event, 'data.object', []);

            $gatewayRefundId = (string) Arr::get($gatewayRefund, 'id', '');

            $refund = $gatewayRefundId !== ''
                ? SCRefund::query()->where('gateway_refund_id', $gatewayRefundId)->first()
                : null;

            if ($refund === null) {
                // Ack and log: the claim stands so redeliveries stay quiet.
                Log::warning('Refund failed event matched no local refund.', [
                    'gateway' => $this->gateway,
                    'event_id' => $eventId,
                    'gateway_refund_id' => $gatewayRefundId,
                ]);

                return;
            }

            $failureReason = Arr::get($gatewayRefund, 'failure_reason');

            $refundService->failFromWebhook($refund, is_string($failureReason) ? $failureReason : null);
        });
    }
}

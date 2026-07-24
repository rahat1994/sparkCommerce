<?php

namespace Rahat1994\SparkCommerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Rahat1994\SparkCommerce\Events\DisputeCreated;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Payments\PaymentEventClaims;

/**
 * A verified `charge.dispute.created` (chargeback opened): the order is
 * located via the dispute's payment intent, flagged
 * (`meta.payment_flag = 'disputed'`) so the admin sees it, and
 * {@see DisputeCreated} announces it (the U14 admin-notification seam).
 * No status moves and no money moves here — disputes are resolved at the
 * processor, not in this codebase.
 */
class HandleDisputeCreated implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * @param  array<string, mixed>  $event  the verified gateway event
     *                                       (`{id, type, data: {object: dispute}}`)
     */
    public function __construct(
        public string $gateway,
        public array $event,
    ) {}

    public function handle(): void
    {
        $eventId = (string) Arr::get($this->event, 'id', '');

        if ($eventId === '') {
            Log::warning('Dispute created event without an id was ignored.', [
                'gateway' => $this->gateway,
            ]);

            return;
        }

        DB::transaction(function () use ($eventId): void {
            if (! PaymentEventClaims::claim($this->gateway, $eventId)) {
                return;
            }

            /** @var array<string, mixed> $dispute */
            $dispute = (array) Arr::get($this->event, 'data.object', []);

            $intentId = (string) Arr::get($dispute, 'payment_intent', '');

            $order = $intentId !== ''
                ? SCOrder::query()->where('transaction_id', $intentId)->first()
                : null;

            if ($order === null) {
                // Ack and log: the claim stands so redeliveries stay quiet.
                Log::warning('Dispute created for an unknown payment intent.', [
                    'gateway' => $this->gateway,
                    'event_id' => $eventId,
                    'payment_intent' => $intentId,
                ]);

                return;
            }

            /** @var SCOrder $locked */
            $locked = SCOrder::query()->lockForUpdate()->findOrFail($order->getKey());

            $locked->meta = array_merge((array) $locked->meta, ['payment_flag' => 'disputed']);
            $locked->save();

            event(new DisputeCreated($locked, $dispute));
        });
    }
}

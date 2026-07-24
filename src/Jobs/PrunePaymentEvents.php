<?php

namespace Rahat1994\SparkCommerce\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Rahat1994\SparkCommerce\Jobs\Concerns\AlertsAdminOnFailure;
use Rahat1994\SparkCommerce\Payments\PaymentEventClaims;

/**
 * Prune webhook claim rows older than thirty days. Processors stop
 * redelivering an event long before that (Stripe retries for up to three
 * days), so an aged claim row can never be needed again for dedup.
 *
 * Scheduled daily by SparkCommerceServiceProvider.
 */
class PrunePaymentEvents implements ShouldQueue
{
    use AlertsAdminOnFailure;
    use Dispatchable;
    use Queueable;

    public function handle(): void
    {
        DB::table(PaymentEventClaims::table())
            ->where('created_at', '<', now()->subDays(30))
            ->delete();
    }
}

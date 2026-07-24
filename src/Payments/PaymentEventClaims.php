<?php

namespace Rahat1994\SparkCommerce\Payments;

use Illuminate\Support\Facades\DB;

/**
 * The webhook event claim table (sc_payment_events): one row per handled
 * (gateway, event_id) pair, guarded by a unique index. Claiming inside the
 * SAME transaction as the side effects makes redelivered webhook events
 * exactly-once: either the claim and every side effect commit together, or
 * none of them do.
 */
class PaymentEventClaims
{
    public static function table(): string
    {
        return config('sparkcommerce.table_prefix') . config('sparkcommerce.payment_events_table_name');
    }

    /**
     * Claim the event with insert-or-ignore semantics: the unique
     * (gateway, event_id) index makes the database itself the arbiter of
     * the duplicate race — a second delivery hits the unique violation,
     * which insertOrIgnore swallows, and 0 affected rows reports "already
     * handled" to the caller.
     */
    public static function claim(string $gateway, string $eventId): bool
    {
        return DB::table(static::table())->insertOrIgnore([
            'gateway' => $gateway,
            'event_id' => $eventId,
            'created_at' => now(),
        ]) === 1;
    }
}

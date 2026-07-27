<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SCCoupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_code',
        'coupon_type',
        'coupon_amount',
        'end_date',
        'start_date',
        'number_of_uses',
        'max_spend',
        'min_spend',
        'min_spend_cents',
        'max_spend_cents',
        'exclude_sale_items',
        'usage_count',
        'usage_limit',
        'usage_limit_per_user',
    ];

    /**
     * `coupon_amount` deliberately has NO integer cast: the legacy model
     * events below divide it back to MAJOR units on retrieval, and an
     * integer cast would truncate fractional major values (e.g. 19.99).
     */
    protected $casts = [
        'name' => 'array',
        'end_date' => 'date',
        'start_date' => 'date',
        'usage_count' => 'integer',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'min_spend_cents' => 'integer',
        'max_spend_cents' => 'integer',
    ];

    protected $with = ['includedProducts'];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . config('sparkcommerce.coupons_table_name');
    }

    public function users()
    {
        $tableName = config('sparkcommerce.table_prefix') . config('sparkcommerce.coupon_user_table_name');

        return $this->belongsToMany(config('auth.providers.users.model'), $tableName)
            ->withPivot('usage_count', 'used_at', 'meta')
            ->withTimestamps();
    }

    public function isValid(): bool
    {
        return $this->end_date === null || $this->end_date->isFuture();
    }

    public function includedProducts()
    {
        $tableName = config('sparkcommerce.table_prefix') . config('sparkcommerce.coupon_included_products_table_name');

        return $this->belongsToMany(SCProduct::class, $tableName, 'coupon_id', 'product_id');
    }

    /**
     * Minimum spend in integer cents, or null when the coupon has none.
     * Prefers the backfilled `min_spend_cents` column; legacy string rows
     * the backfill has not touched (e.g. written by the admin form) fall
     * back to `min_spend` major units x100.
     */
    public function minSpendCents(): ?int
    {
        return $this->spendLimitCents('min_spend_cents', 'min_spend');
    }

    /**
     * Maximum spend in integer cents, or null when the coupon has none.
     */
    public function maxSpendCents(): ?int
    {
        return $this->spendLimitCents('max_spend_cents', 'max_spend');
    }

    /**
     * Whether the coupon still has global redemptions left. A NULL or
     * non-positive `usage_limit` means unlimited. This is only a cheap
     * read-time check — the atomic arbiter under concurrency is
     * {@see claimSingleUse()}.
     */
    public function hasRemainingGlobalUses(): bool
    {
        $limit = (int) ($this->usage_limit ?? 0);

        if ($limit <= 0) {
            return true;
        }

        return (int) ($this->usage_count ?? 0) < $limit;
    }

    /**
     * Atomically claim one global use: a guarded increment
     * (`usage_count < usage_limit` in the UPDATE's WHERE) whose
     * affected-rows count is the ONLY safe arbiter of the limit race.
     * Unlimited coupons (NULL/0 limit) claim nothing and always succeed.
     */
    public function claimSingleUse(): bool
    {
        $limit = (int) ($this->usage_limit ?? 0);

        if ($limit <= 0) {
            return true;
        }

        $claimed = static::query()
            ->whereKey($this->getKey())
            ->where('usage_count', '<', $limit)
            ->increment('usage_count') === 1;

        if ($claimed) {
            $this->usage_count = (int) ($this->usage_count ?? 0) + 1;
        }

        return $claimed;
    }

    /**
     * Give a claimed use back (guarded decrement, floored at zero). Called
     * when the order holding the reservation is cancelled or expires.
     */
    public function releaseSingleUse(): void
    {
        $released = static::query()
            ->whereKey($this->getKey())
            ->where('usage_count', '>', 0)
            ->decrement('usage_count') === 1;

        if ($released) {
            $this->usage_count = max(0, (int) ($this->usage_count ?? 0) - 1);
        }
    }

    /**
     * How many times the given user has redeemed this coupon, read from the
     * sc_coupon_user pivot.
     */
    public function usesByUser(Model $user): int
    {
        $tableName = config('sparkcommerce.table_prefix') . config('sparkcommerce.coupon_user_table_name');

        return (int) DB::table($tableName)
            ->where('coupon_id', $this->getKey())
            ->where('user_id', $user->getKey())
            ->sum('usage_count');
    }

    /**
     * Record a completed redemption for the order's user: upsert the
     * sc_coupon_user pivot (unique (coupon_id, user_id) semantics — the
     * existing row's usage_count is incremented, otherwise a row is
     * inserted).
     *
     * Global counter: a single-use coupon claimed at checkout (the order's
     * `discount` json says `reserved`) already holds its `usage_count`
     * slot, so only un-reserved redemptions count globally here. Called by
     * the free-order checkout path (U10) and the paid webhook (U12).
     */
    public function recordUsageFor(SCOrder $order): void
    {
        $userId = $order->user_id;

        if ($userId === null) {
            Log::warning('SCCoupon::recordUsageFor skipped: order has no user.', [
                'coupon_id' => $this->getKey(),
                'order_id' => $order->getKey(),
            ]);

            return;
        }

        $tableName = config('sparkcommerce.table_prefix') . config('sparkcommerce.coupon_user_table_name');
        $now = now();

        $updated = DB::table($tableName)
            ->where('coupon_id', $this->getKey())
            ->where('user_id', $userId)
            ->update([
                'usage_count' => DB::raw('usage_count + 1'),
                'used_at' => $now,
                'updated_at' => $now,
            ]);

        if ($updated === 0) {
            DB::table($tableName)->insert([
                'coupon_id' => $this->getKey(),
                'user_id' => $userId,
                'usage_count' => 1,
                'used_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (data_get($order->discount, 'reserved') !== true) {
            static::query()->whereKey($this->getKey())->increment('usage_count');
            $this->usage_count = (int) ($this->usage_count ?? 0) + 1;
        }
    }

    /**
     * Cents fallback boundary for the spend limits (see minSpendCents()).
     */
    protected function spendLimitCents(string $centsColumn, string $legacyColumn): ?int
    {
        $cents = $this->getAttribute($centsColumn);

        if ($cents !== null) {
            return (int) $cents;
        }

        $legacy = $this->getAttribute($legacyColumn);

        if ($legacy === null || $legacy === '' || ! is_numeric($legacy)) {
            return null;
        }

        return (int) round(((float) $legacy) * 100);
    }

    protected static function booted(): void
    {
        static::creating(fn ($coupon) => self::turnPriceIntoCents($coupon));
        static::updating(fn ($coupon) => self::turnPriceIntoCents($coupon));

        static::retrieved(function (SCCoupon $coupon) {
            $coupon->coupon_amount = $coupon->coupon_amount / (int) config('sparkcommerce.decimal_value');
        });
    }

    protected static function turnPriceIntoCents(SCCoupon $coupon)
    {
        $coupon->coupon_amount = $coupon->coupon_amount * (int) config('sparkcommerce.decimal_value');
    }
}

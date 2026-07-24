<?php

namespace Rahat1994\SparkCommerce\Models;

use Cknow\Money\Casts\MoneyIntegerCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rahat1994\SparkCommerce\Enums\RefundStatus;

/**
 * One refund attempt against an order's captured payment (U13).
 *
 * `amount_cents` follows the order money boundary: written as integer
 * cents, read back as a \Cknow\Money\Money object (currency from the
 * `currency` column). A NULL `initiated_by` marks a system refund (the
 * late-payment auto-refund); otherwise it is the acting admin's user id.
 */
class SCRefund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'gateway',
        'gateway_refund_id',
        'amount_cents',
        'currency',
        'status',
        'restocked',
        'initiated_by',
        'failure_reason',
    ];

    protected $casts = [
        'status' => RefundStatus::class,
        'restocked' => 'boolean',
        'initiated_by' => 'integer',
        'amount_cents' => MoneyIntegerCast::class . ':currency',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . config('sparkcommerce.refunds_table_name');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SCOrder::class, 'order_id');
    }
}

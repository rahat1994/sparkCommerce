<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class SCOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'items',
        'shipping_address',
        'billing_address',
        'shipping_method',
        'total_amount',
        'tracking_number',
        'transaction_id',
        'discount',
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'shipping_status',
        'payment_method',
        'vendor_id',
        'meta',
    ];

    protected $casts = [
        'items' => 'array',
        'discount' => 'array',
        'meta' => 'array',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . config('sparkcommerce.orders_table_name');
    }

    /**
     * The user who placed the order.
     *
     * Decision: no user FKs in v1. The `user_id` columns across SparkCommerce
     * stay `unsignedInteger` without a database foreign key constraint, so
     * this relation is resolved by Eloquent only.
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    public function vendor()
    {
        $vendorModel = config('sparkcommerce.vendor_model');

        if ($vendorModel === null) {
            throw new RuntimeException('sparkcommerce.vendor_model is not configured; install/configure a vendor package.');
        }

        return $this->belongsTo($vendorModel);
    }
}

<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCOrder extends Model
{
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
    ];

    protected $casts = [
        'items' => 'array',
        'discount' => 'array',
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
}

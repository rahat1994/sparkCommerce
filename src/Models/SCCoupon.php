<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCCoupon extends Model
{

    // $table->string('coupon_code');
    // $table->string('coupon_type');
    // $table->string('coupon_amount');
    // $table->date('end_date');
    // $table->date('start_date');
    // $table->boolean('number_of_uses')->comment('0: unlimited, 1: one time, If cart is eligible or conditions are met, coupon applies once. ie: If you set the coupon to offer Buy 2 Get 1, you get one free product. Moving more items to the cart will not make it eligible to get more free products.');
    // $table->string('max_spend');
    // $table->string('min_spend');
    // $table->boolean('exclude_sale_items')->default(0);
    // $table->json('included_products');
    // $table->json('excluded_products');
    // $table->json('included_categories');
    // $table->json('excluded_categories');
    // $table->json('included_customers');
    // $table->string('usage_limit')->comment('how many times the coupon can be used');
    // $table->string('usage_limit_per_user')->comment('how many times the coupon can be used by a single user');

    protected $fillable = [
        'coupon_code',
        'coupon_type',
        'coupon_amount',
        'end_date',
        'start_date',
        'number_of_uses',
        'max_spend',
        'min_spend',
        'exclude_sale_items',
        'included_products',
        'excluded_products',
        'included_categories',
        'excluded_categories',
        'included_customers',
        'usage_limit',
        'usage_limit_per_user',
    ];

    // add a cast of name from string to array
    protected $casts = [
        'name' => 'array',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return 'sc_coupons';
    }
}

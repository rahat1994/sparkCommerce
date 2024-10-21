<?php

namespace Rahat1994\SparkCommerce\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SCCoupon extends Model
{
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

    public function users()
    {
        return $this->belongsToMany(User::class, 'coupon_user')
            ->withPivot('usage_count')
            ->withTimestamps();
    }

    public function isValid()
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function includedProducts()
    {
        return $this->belongsToMany(SCProduct::class, 'coupon_included_product');
    }

    // public function excludedProducts()
    // {
    //     return $this->belongsToMany(SCProduct::class, 'coupon_excluded_product');
    // }
}

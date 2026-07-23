<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'exclude_sale_items',
        'usage_limit',
        'usage_limit_per_user',
    ];

    // add a cast of name from string to array
    protected $casts = [
        'name' => 'array',
        'end_date' => 'date',
        'start_date' => 'date',
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

    // public function excludedProducts()
    // {
    //     return $this->belongsToMany(SCProduct::class, 'coupon_excluded_product');
    // }

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

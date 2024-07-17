<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCAnonymousCart extends Model
{
    protected $fillable = [
        'reference',
        'cart_content',
    ];

    protected $casts = [
        'cart_content' => 'array',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . config('sparkcommerce.anonymous_carts_table_name');
    }
}

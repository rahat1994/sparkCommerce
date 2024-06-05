<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCReview extends Model
{
    protected $fillable = [
        'product_id',
        'title',
        'content',
        'rating',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . config('sparkcommerce.product_reviews_table_name');
    }
}

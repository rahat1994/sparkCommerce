<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCProductVariation extends Model
{
    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . 'products';
    }

    public function product()
    {
        return $this->belongsTo(ScProduct::class, 'product_id', 'id');
    }
}

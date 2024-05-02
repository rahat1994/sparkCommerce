<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Tags\HasTags;

class SCProduct extends Model
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

    public function variations()
    {
        return $this->hasMany(ScProductVariation::class, 'product_id', 'id');
    }
}

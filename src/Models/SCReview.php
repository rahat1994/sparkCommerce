<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCReview extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'order_column'];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return 'tags';
    }
}

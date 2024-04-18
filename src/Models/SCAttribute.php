<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCAttribute extends Model
{
    protected $fillable = ['product_id', 'name', 'value'];

    protected $casts = [
        'product_id' => 'integer',
        'name' => 'string',
        'value' => 'array',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . 'attributes';
    }

    public function product()
    {
        return $this->belongsTo(ScProduct::class, 'product_id', 'id');
    }
}

<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCCategory extends Model
{
    protected $fillable = ['name', 'user_id'];

    protected $casts = [
        'name' => 'string',
        'user_id' => 'integer',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . 'category';
    }

    public function product()
    {
        // return $this->hasManyThrough(ScProduct::class);
    }
}

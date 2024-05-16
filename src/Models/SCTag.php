<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCTag extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'order_column'];

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
        return 'tags';
    }
}

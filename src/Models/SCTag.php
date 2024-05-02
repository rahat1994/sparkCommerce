<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Tags\HasTags;

class SCTag extends Model
{
    protected $fillable = ['name','slug','type', 'order_column'];

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

<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class SCProduct extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use HasTags;
    use InteractsWithMedia;

    protected $casts = [
        'product_attributes' => 'array',
    ];

    protected $fillable = [
        'name',
        'description',
        'product_type',
        'slug',
        'sku',
        'stock_quantity',
        'allow_backorders',
        'low_stock_threshold',
        'weight',
        'height',
        'width',
        'length',
        'product_attributes',
    ];

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

<?php

namespace Rahat1994\SparkCommerce\Models;

use Illuminate\Database\Eloquent\Model;

class SCProductVariation extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'enabled',
        'downloadable',
        'virtual',
        'variation_title',
        'regular_price',
        'sale_price',
        'description',
        'weight',
        'height',
        'width',
        'length',
        'stock_quantity',
        'attribute_combination',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'downloadable' => 'boolean',
            'virtual' => 'boolean',
            'attribute_combination' => 'array',
        ];
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . config('sparkcommerce.product_variants_table_name');
    }

    public function product()
    {
        return $this->belongsTo(SCProduct::class, 'product_id', 'id');
    }
}

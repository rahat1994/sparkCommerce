<?php

namespace Rahat1994\SparkCommerce\Models;

use App\Models\User;
use Binafy\LaravelCart\Cartable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class SCProduct extends Model implements \Spatie\MediaLibrary\HasMedia, Cartable
{
    use HasTags;
    use InteractsWithMedia;
    use Sluggable;

    protected $casts = [
        'product_attributes' => 'array',
    ];

    protected $fillable = [
        'name',
        'user_id',
        'description',
        'product_type',
        'slug',
        'price',
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

    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * Return the sluggable configuration array for this model.
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('sparkcommerce.table_prefix') . 'products';
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function categories()
    {
        return $this->belongsToMany(SCCategory::class, config('sparkcommerce.table_prefix') . config('sparkcommerce.category_product_table_name'), 'product_id', 'category_id');
    }

    public function variations()
    {
        return $this->hasMany(ScProductVariation::class, 'product_id', 'id');
    }
}

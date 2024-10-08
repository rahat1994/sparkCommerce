<?php

namespace Rahat1994\SparkCommerce\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;

class SCCategory extends Model implements \Spatie\MediaLibrary\HasMedia
{
    use InteractsWithMedia;
    use Sluggable;

    protected $fillable = ['name', 'user_id', 'parent_id'];

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
        return config('sparkcommerce.table_prefix') . config('sparkcommerce.categories_table_name');
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    public function product()
    {
        // return $this->hasManyThrough(ScProduct::class);
    }

    public function parent()
    {
        return $this->belongsTo(SCCategory::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(SCCategory::class, 'parent_id', 'id');
    }

    // recursive, loads all descendants
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }
}

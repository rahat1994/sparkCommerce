<?php

namespace Rahat1994\SparkCommerce\Models;

use Binafy\LaravelCart\Cartable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Rahat1994\SparkCommerce\Enums\BackorderPolicy;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class SCProduct extends Model implements Cartable, HasMedia
{
    use HasFactory;
    use HasTags;
    use InteractsWithMedia;
    use Sluggable;

    protected function casts(): array
    {
        return [
            'product_attributes' => 'array',
            'manage_product_stock' => 'boolean',
            'stock_quantity' => 'integer',
            'should_allow_backorders' => BackorderPolicy::class,
        ];
    }

    protected $fillable = [
        'name',
        'user_id',
        'vendor_id', // column added by the multivendor package
        'description',
        'product_type',
        'slug',
        'regular_price',
        'sale_price',
        'sku',
        'stock_quantity',
        'manage_product_stock',
        'should_allow_backorders',
        'low_stock_threshold',
        'weight',
        'height',
        'width',
        'length',
        'product_attributes',
        'purchase_note',
    ];

    public function getPrice(): float
    {
        return $this->sale_price ?: $this->regular_price;
    }

    public function isStockManaged(): bool
    {
        return (bool) $this->manage_product_stock;
    }

    /**
     * A NULL `should_allow_backorders` column (legacy rows) means backorders
     * are not allowed.
     */
    public function backorderPolicy(): BackorderPolicy
    {
        return $this->should_allow_backorders ?? BackorderPolicy::DoNotAllow;
    }

    /**
     * Can this quantity be sold right now? True when stock management is
     * off, when the backorder policy allows overselling, or when the
     * on-hand stock covers the quantity.
     */
    public function isAvailable(int $quantity): bool
    {
        if (! $this->isStockManaged()) {
            return true;
        }

        if ($this->backorderPolicy()->allowsBackorder()) {
            return true;
        }

        return (int) $this->stock_quantity >= $quantity;
    }

    /**
     * Atomically claim stock for this quantity; false means insufficient.
     *
     * DoNotAllow uses the guarded-decrement form — the single UPDATE with a
     * `stock_quantity >= quantity` predicate plus the affected-rows check IS
     * the concurrency guarantee (only one of two competing checkouts can
     * match the row; no explicit lock needed). Backorder-allowing policies
     * decrement unguarded, and stock management off is a full bypass.
     */
    public function reserveStock(int $quantity): bool
    {
        if (! $this->isStockManaged()) {
            return true;
        }

        if ($this->backorderPolicy()->allowsBackorder()) {
            static::query()->whereKey($this->getKey())->decrement('stock_quantity', $quantity);

            return true;
        }

        $affected = static::query()
            ->whereKey($this->getKey())
            ->where('stock_quantity', '>=', $quantity)
            ->decrement('stock_quantity', $quantity);

        return $affected === 1;
    }

    /**
     * Give reserved stock back (order cancelled/expired before payment).
     * A no-op when stock is not managed — the bypass must hold on release
     * too, otherwise unmanaged products would accumulate phantom stock.
     */
    public function releaseStock(int $quantity): void
    {
        if (! $this->isStockManaged()) {
            return;
        }

        static::query()->whereKey($this->getKey())->increment('stock_quantity', $quantity);
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
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id', 'id');
    }

    public function categories()
    {
        return $this->belongsToMany(SCCategory::class, config('sparkcommerce.table_prefix') . config('sparkcommerce.category_product_table_name'), 'product_id', 'category_id');
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function variations()
    {
        return $this->hasMany(SCProductVariation::class, 'product_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany(SCReview::class, 'product_id', 'id');
    }

    protected static function booted(): void
    {
        static::creating(fn ($product) => self::turnPriceIntoCents($product));
        static::updating(fn ($product) => self::turnPriceIntoCents($product));

        static::retrieved(function (SCProduct $product) {
            $product->regular_price = $product->regular_price / (int) config('sparkcommerce.decimal_value');
            if ($product->sale_price) {
                $product->sale_price = $product->sale_price / (int) config('sparkcommerce.decimal_value');
            }
        });
    }

    protected static function turnPriceIntoCents(SCProduct $product)
    {
        $product->regular_price = $product->regular_price * (int) config('sparkcommerce.decimal_value');

        if ($product->sale_price) {
            $product->sale_price = $product->sale_price * (int) config('sparkcommerce.decimal_value');
        }
    }
}

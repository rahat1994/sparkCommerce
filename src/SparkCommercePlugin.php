<?php

namespace Rahat1994\SparkCommerce;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Rahat1994\SparkCommerce\Filament\Resources\CategoryResource;
use Rahat1994\SparkCommerce\Filament\Resources\CouponResource;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Rahat1994\SparkCommerce\Filament\Resources\ReviewResource;

class SparkCommercePlugin implements Plugin
{
    protected array $resources = [];

    final public function __construct(array $resources = [])
    {
        $this->setPanelResource($resources);
    }

    public function getId(): string
    {
        return 'sparkcommerce';
    }

    public function register(Panel $panel): void
    {
        $panel->resources($this->resources);
    }

    public function boot(Panel $panel): void {}

    public function setPanelResource(array $resources = []): void
    {
        $this->resources = $resources;
    }

    public static function make(array $resources = [
        ProductResource::class,
        CategoryResource::class,
        ReviewResource::class,
        OrderResource::class,
        CouponResource::class,
    ]): static
    {
        return app(static::class, ['resources' => $resources]);

    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function hasProductResource(): bool
    {
        return true;
    }
}

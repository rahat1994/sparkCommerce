<?php

namespace Rahat1994\SparkCommerce;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Rahat1994\SparkCommerce\Filament\Resources\CategoryResource;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Rahat1994\SparkCommerce\Filament\Resources\ReviewResource;
use Rahat1994\SparkCommerce\Filament\Resources\TagResource;

class SparkCommercePlugin implements Plugin
{
    public function getId(): string
    {
        return 'sparkcommerce';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            ProductResource::class,
            CategoryResource::class,
            TagResource::class,
            ReviewResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): static
    {
        return app(static::class);
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

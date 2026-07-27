<?php

namespace Rahat1994\SparkCommerce\Tests\Fixtures;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Rahat1994\SparkCommerce\Filament\Resources\CategoryResource;
use Rahat1994\SparkCommerce\Filament\Resources\CouponResource;
use Rahat1994\SparkCommerce\Filament\Resources\OrderResource;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Rahat1994\SparkCommerce\Filament\Resources\ReviewResource;
use Rahat1994\SparkCommerce\Filament\Resources\UserResource;
use Rahat1994\SparkCommerce\SparkCommercePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->plugins([
                SparkCommercePlugin::make([
                    ProductResource::class,
                    CategoryResource::class,
                    ReviewResource::class,
                    OrderResource::class,
                    CouponResource::class,
                    UserResource::class,
                ]),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

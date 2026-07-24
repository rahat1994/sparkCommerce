<?php

namespace Rahat1994\SparkCommerce;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Asset;
use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Filament\Support\Facades\FilamentIcon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportTesting\Testable;
use Rahat1994\SparkCommerce\Commands\SCPublishRolesCommand;
use Rahat1994\SparkCommerce\Commands\SparkCommercePublishMigrations;
use Rahat1994\SparkCommerce\Events\DisputeCreated;
use Rahat1994\SparkCommerce\Events\FreeOrderPlaced;
use Rahat1994\SparkCommerce\Events\LatePaymentReceived;
use Rahat1994\SparkCommerce\Events\OrderAutoRefunded;
use Rahat1994\SparkCommerce\Events\OrderRefunded;
use Rahat1994\SparkCommerce\Events\OrderTransitioned;
use Rahat1994\SparkCommerce\Events\PaymentAmountMismatch;
use Rahat1994\SparkCommerce\Events\PaymentNeedsReconciliation;
use Rahat1994\SparkCommerce\Events\ProductBackordered;
use Rahat1994\SparkCommerce\Events\RefundFailed;
use Rahat1994\SparkCommerce\Events\WebhookSignatureFailing;
use Rahat1994\SparkCommerce\Jobs\PrunePaymentEvents;
use Rahat1994\SparkCommerce\Jobs\ReconcileStuckPayments;
use Rahat1994\SparkCommerce\Listeners\AutoRefundLatePayment;
use Rahat1994\SparkCommerce\Listeners\ReleaseCouponReservation;
use Rahat1994\SparkCommerce\Listeners\ReleaseReservedStock;
use Rahat1994\SparkCommerce\Listeners\SendAdminPaymentAlert;
use Rahat1994\SparkCommerce\Listeners\SendBackorderNotification;
use Rahat1994\SparkCommerce\Listeners\SendOrderPaidNotifications;
use Rahat1994\SparkCommerce\Listeners\SendOrderRefundedNotification;
use Rahat1994\SparkCommerce\Payments\PaymentGatewayManager;
use Rahat1994\SparkCommerce\Testing\TestsSparkCommerce;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SparkCommerceServiceProvider extends PackageServiceProvider
{
    public static string $name = 'sparkcommerce';

    public static string $viewNamespace = 'sparkcommerce';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package->name(static::$name)
            ->hasCommands($this->getCommands())
            // Gateway notification entry. package-tools loads route files
            // via loadRoutesFrom, OUTSIDE every middleware group — so no
            // CSRF/session applies to processor POSTs (verified by test).
            ->hasRoutes('webhooks')
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('rahat1994/sparkcommerce');
            });

        $configFileName = $package->shortName();

        if (file_exists($package->basePath("/../config/{$configFileName}.php"))) {
            $package->hasConfigFile();
        }

        if (file_exists($package->basePath('/../database/migrations'))) {
            $package->hasMigrations($this->getMigrations());
        }

        if (file_exists($package->basePath('/../resources/lang'))) {
            $package->hasTranslations();
        }

        if (file_exists($package->basePath('/../resources/views'))) {
            $package->hasViews(static::$viewNamespace);
        }
    }

    public function packageRegistered(): void
    {
        // Payment gateway module (R13): one manager instance app-wide, so
        // adopter ->extend() registrations are seen by every consumer.
        $this->app->singleton(PaymentGatewayManager::class, fn ($app) => new PaymentGatewayManager($app));
        $this->app->alias(PaymentGatewayManager::class, 'sparkcommerce.payments');
    }

    public function packageBooted(): void
    {
        $this->registerPanelAccessGate();
        $this->registerScheduledJobs();

        // Stock released on cancellation/expiry of unpaid orders (R9).
        Event::listen(OrderTransitioned::class, ReleaseReservedStock::class);

        // Reserved single-use coupons released on the same transitions (R10).
        Event::listen(OrderTransitioned::class, ReleaseCouponReservation::class);

        // A payment landing on an expired order is returned automatically
        // (KTD13, U13) — never restocking, since expiry already released it.
        Event::listen(LatePaymentReceived::class, AutoRefundLatePayment::class);

        // Transactional mails (R15, U14): every notification is queued and
        // afterCommit. Order mails hang on the paid transition (never on
        // creation, expiry, or cancellation of unpaid orders); the refund
        // mail on the refund-succeeded seam; every operational payment
        // event maps to the one parameterized admin alert.
        Event::listen(OrderTransitioned::class, SendOrderPaidNotifications::class);
        Event::listen(OrderRefunded::class, SendOrderRefundedNotification::class);
        Event::listen(ProductBackordered::class, SendBackorderNotification::class);

        foreach ([
            PaymentAmountMismatch::class,
            OrderAutoRefunded::class,
            DisputeCreated::class,
            RefundFailed::class,
            WebhookSignatureFailing::class,
            PaymentNeedsReconciliation::class,
            FreeOrderPlaced::class,
        ] as $operationalEvent) {
            Event::listen($operationalEvent, SendAdminPaymentAlert::class);
        }

        // Asset Registration
        FilamentAsset::register(
            $this->getAssets(),
            $this->getAssetPackageName()
        );

        FilamentAsset::registerScriptData(
            $this->getScriptData(),
            $this->getAssetPackageName()
        );

        // Icon Registration
        FilamentIcon::register($this->getIcons());

        // Handle Stubs
        if (app()->runningInConsole()) {
            foreach (app(Filesystem::class)->files(__DIR__ . '/../stubs/') as $file) {
                $this->publishes([
                    $file->getRealPath() => base_path("stubs/sparkcommerce/{$file->getFilename()}"),
                ], 'sparkcommerce-stubs');
            }
        }

        // Testing
        Testable::mixin(new TestsSparkCommerce);
    }

    /**
     * Payment upkeep tasks (U12), registered lazily so the Schedule is only
     * touched when the host actually resolves it: reconciliation every
     * thirty minutes (catches dropped paid-webhooks) and a daily prune of
     * webhook claim rows older than thirty days.
     */
    protected function registerScheduledJobs(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new ReconcileStuckPayments)->everyThirtyMinutes();
            $schedule->job(new PrunePaymentEvents)->daily();
        });
    }

    /**
     * Gate deciding who may operate the SparkCommerce admin resources.
     *
     * Resolution order: an invokable `sparkcommerce.panel_gate` class-string
     * overrides everything; otherwise the user must hold the
     * `sparkcommerce.admin_role` role. A user model without roles support is
     * denied by default (secure default).
     */
    protected function registerPanelAccessGate(): void
    {
        Gate::define('access-sparkcommerce-admin', function (?Authenticatable $user = null): bool {
            $panelGate = config('sparkcommerce.panel_gate');

            if (is_string($panelGate) && class_exists($panelGate)) {
                return (bool) app($panelGate)($user);
            }

            $adminRole = config('sparkcommerce.admin_role');

            if (! is_string($adminRole) || $adminRole === '') {
                return false;
            }

            if ($user === null || ! method_exists($user, 'hasRole')) {
                return false;
            }

            return $user->hasRole($adminRole);
        });
    }

    protected function getAssetPackageName(): ?string
    {
        return 'rahat1994/sparkcommerce';
    }

    /**
     * @return array<Asset>
     */
    protected function getAssets(): array
    {
        return [
            // AlpineComponent::make('sparkcommerce', __DIR__ . '/../resources/dist/components/sparkcommerce.js'),
            Css::make('sparkcommerce-styles', __DIR__ . '/../resources/dist/sparkcommerce.css'),
            Js::make('sparkcommerce-scripts', __DIR__ . '/../resources/dist/sparkcommerce.js'),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getCommands(): array
    {
        return [
            SparkCommercePublishMigrations::class,
            SCPublishRolesCommand::class,
        ];
    }

    /**
     * @return array<string>
     */
    protected function getIcons(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    protected function getRoutes(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getScriptData(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getMigrations(): array
    {
        return [
            'create_sc_products_table',
            'complete_sc_product_variations_table',
            'create_sc_categories_table',
            'create_sc_reviews_table',
            'create_sc_category_products_table',
            'create_sc_orders_table',
            'backfill_order_statuses',
            'add_payment_columns_to_orders',
            'create_sc_anonymous_carts_table',
            'create_sc_coupons_table',
            'create_sc_coupon_user_table',
            'create_sc_coupon_included_products_table',
            'convert_stock_quantity_to_integer',
            'complete_coupon_schema',
            'create_sc_payment_events_table',
            'create_sc_refunds_table',
        ];
    }
}

# Sparkcommerce: Ecommerce plugin for filament

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rahat1994/sparkcommerce.svg?style=flat-square)](https://packagist.org/packages/rahat1994/sparkcommerce)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/rahat1994/sparkcommerce/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/rahat1994/sparkcommerce/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/rahat1994/sparkcommerce/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/rahat1994/sparkcommerce/actions?query=workflow%3A"Fix+PHP+Code+Styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/rahat1994/sparkcommerce.svg?style=flat-square)](https://packagist.org/packages/rahat1994/sparkcommerce)

SparkCommerce adds a complete ecommerce admin to your Filament panel: products, categories, orders, coupons, reviews, refunds, and a payment gateway module with a shipped Stripe driver. It pairs with [`rahat1994/sparkcommerce-rest-routes`](https://github.com/rahat1994/sparkcommerce-rest-routes), which exposes the storefront REST API (cart, checkout, orders) for your own frontend.

## Requirements

- PHP `^8.3`
- Laravel `13`
- Filament `~5.0` (a working Filament panel)
- A queue worker and the scheduler running in production (see [Required runtime services](#required-runtime-services))

The package pulls in `spatie/laravel-permission` (roles), `binafy/laravel-cart` (cart tables), `cknow/laravel-money` (money handling), `stripe/stripe-php` (the Stripe driver), and the Spatie media-library and tags Filament plugins.

## Installation

Follow the steps **in this order**.

### 1. Require the package

```bash
composer require rahat1994/sparkcommerce
```

### 2. Publish the config file

```bash
php artisan vendor:publish --tag="sparkcommerce-config"
```

This creates `config/sparkcommerce.php`. See the [configuration reference](#configuration-reference) below.

### 3. Publish the migrations

The package ships a helper command that publishes its own migrations **plus** the migrations of the third-party packages it depends on (tags, media library, laravel-cart):

```bash
php artisan sparkcommerce:publish-migrations
```

> **Run this command only once.** SparkCommerce's own migrations are safe to re-publish (see [Re-publishing migrations](#re-publishing-migrations)), but `binafy/laravel-cart` re-dates its migration files on every publish, so a second run creates duplicate cart migrations. If you ever need to re-publish only the SparkCommerce migrations, use the tag directly:
>
> ```bash
> php artisan vendor:publish --tag="sparkcommerce-migrations"
> ```

You also need the `spatie/laravel-permission` tables (they are not covered by the command above):

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

### 4. Run the migrations

```bash
php artisan migrate
```

Two migrations (`backfill_order_statuses` and `add_payment_columns_to_orders`) first **survey** your existing order rows. On a fresh install there is nothing to survey and they simply run. On a database with existing orders they abort — before writing anything — if they find rows they cannot convert safely, and the exception lists the offending row ids. See [UPGRADE.md](UPGRADE.md) for what to do in that case.

### 5. Create the admin role

```bash
php artisan sc:publish-roles
```

This creates the role named by `sparkcommerce.admin_role` (default: `sc_admin`). Then give that role to your admin user, for example with tinker:

```bash
php artisan tinker --execute '\App\Models\User::where("email", "admin@example.com")->first()->assignRole("sc_admin");'
```

Every SparkCommerce Filament resource is protected by the `access-sparkcommerce-admin` gate the package registers: a user passes it when they hold the `sc_admin` role (or when you configure a `panel_gate` override — see the config reference). A user model without roles support is denied by default.

### 6. Prepare your User model

Your user model needs:

- `Spatie\Permission\Traits\HasRoles` — so the role check works.
- `Filament\Models\Contracts\FilamentUser` with `canAccessPanel()` — so Filament lets the user into the panel at all.
- `Illuminate\Notifications\Notifiable` — order confirmation and admin alert mails are notifications.
- `Laravel\Sanctum\HasApiTokens` — only when you also install the REST API package.

```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // when using sparkcommerce-rest-routes
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasRoles, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        // Let SparkCommerce admins into the panel. If your panel also hosts
        // other users, widen this check to match your own rules — the
        // SparkCommerce resources stay protected by the package's
        // `access-sparkcommerce-admin` gate either way.
        return $this->hasRole(config('sparkcommerce.admin_role', 'sc_admin'));
    }
}
```

### 7. Register the plugin on your panel

Add `SparkCommercePlugin::make()` to your panel provider. A complete example:

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
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
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->plugins([
                SparkCommercePlugin::make(),
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
```

`SparkCommercePlugin::make()` registers the Product, Category, Review, Order, and Coupon resources. You can pass your own resource list to `make([...])` to swap or drop resources.

### 8. Required runtime services

These are **not optional**. Without them, orders are created but never confirmed, and unpaid orders are never cleaned up.

**Queue worker.** All mail notifications and all webhook payment jobs are queued. Run a worker:

```bash
php artisan queue:work
```

**Scheduler.** The package registers scheduled jobs:

| Job | Schedule | Registered by | What it does |
|---|---|---|---|
| `ReconcileStuckPayments` | every 30 minutes | this package | Asks the gateway about overdue unpaid orders that have a payment reference. A payment that actually succeeded is flagged for the admin (`PaymentNeedsReconciliation`) — the webhook pipeline stays the only thing that marks orders paid. |
| `PrunePaymentEvents` | daily | this package | Deletes webhook claim rows older than 30 days. |
| `ExpireStaleOrders` | every 5 minutes | the REST API package | Expires unpaid orders past their TTL and releases their stock and coupon reservations. |

Run the scheduler with cron (`* * * * * php artisan schedule:run`) or, in development:

```bash
php artisan schedule:work
```

## Stripe setup

The default payment gateway is Stripe (`sparkcommerce.payments.default` = `stripe`). It creates one PaymentIntent per order on your platform account.

### 1. Environment keys

```dotenv
SPARKCOMMERCE_STRIPE_SECRET_KEY=sk_test_...
SPARKCOMMERCE_STRIPE_WEBHOOK_SECRET=whsec_...
```

These fill `sparkcommerce.payments.gateways.stripe.secret_key` and `.webhook_secret`. Without the webhook secret, **every** webhook is rejected with a 400 (the driver denies by default).

### 2. Webhook endpoint

Create a webhook endpoint in the Stripe dashboard pointing at:

```
POST https://your-app.example/sc/webhooks/stripe
```

The route is registered by this package (`sc/webhooks/{gateway}`, route name `sparkcommerce.webhooks.gateway`) outside the `web` middleware group, so no CSRF token or session applies. Signature verification inside the driver is the only gate.

### 3. Events to subscribe

Subscribe the endpoint to exactly these event types (this is the full list the Stripe driver handles; all other event types are acknowledged and ignored):

- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `charge.refunded`
- `refund.failed`
- `charge.dispute.created`

### 4. Local development with the Stripe CLI

```bash
stripe listen --forward-to https://your-app.test/sc/webhooks/stripe \
  --events payment_intent.succeeded,payment_intent.payment_failed,charge.refunded,refund.failed,charge.dispute.created
```

The CLI prints a `whsec_...` signing secret; put it in `SPARKCOMMERCE_STRIPE_WEBHOOK_SECRET`. Then trigger test payments from your storefront (or the example checkout page shipped with the REST API package).

## Configuration reference

All keys in `config/sparkcommerce.php`:

| Key | Default | What it does |
|---|---|---|
| `decimal_value` | `100` | Minor units per major unit. Product prices and fixed coupon amounts are stored multiplied by this value and divided back on read. |
| `vendor_model` | `null` | Class name of the vendor (shop) model. Stays `null` in the standalone package; the multivendor add-on sets it. |
| `admin_role` | `'sc_admin'` | Role name that grants access to the SparkCommerce admin resources. Created by `sc:publish-roles`. |
| `panel_gate` | `null` | Optional invokable class-string. When set, it is resolved from the container and called with the current user, replacing the `admin_role` check. |
| `vendor_owner_role` | `null` | Role name for vendor (shop) owners. Stays `null` in the standalone package. |
| `default_currency` | `'USD'` | ISO 4217 code stamped onto new orders at write time (checkout and the payment-columns backfill). The schema never hard-codes a currency. |
| `allow_free_orders` | `true` | When `true`, a checkout whose grand total is zero completes without a payment: the order is created and immediately marked Paid, and a `FreeOrderPlaced` event fires. When `false`, zero-total checkouts are rejected with a validation error. |
| `order_ttl` | `90` | Minutes an `awaiting_payment` order stays payable before the expiry sweep moves it to Expired (releasing its stock and coupon reservations). Keep it well under 24 hours: Stripe keeps idempotency keys for 24h, so a retried payment must never target an order that no longer exists. |
| `max_open_orders` | `5` | Maximum `awaiting_payment` orders one user may hold open at the same time. Checkout rejects beyond this cap (bounds how much stock one user can lock up). |
| `payments.default` | `'stripe'` | The gateway driver checkout uses. `stripe` ships with the package; `fake` backs the test suites. |
| `payments.gateways.stripe.secret_key` | `env('SPARKCOMMERCE_STRIPE_SECRET_KEY')` | Stripe secret API key. |
| `payments.gateways.stripe.webhook_secret` | `env('SPARKCOMMERCE_STRIPE_WEBHOOK_SECRET')` | Stripe webhook signing secret. Without it, all Stripe webhooks answer 400. |
| `payments.gateways.fake.webhook_secret` | `env('SPARKCOMMERCE_FAKE_WEBHOOK_SECRET')` | Shared secret for the fake test driver's webhooks. Only ever set in test environments. |
| `refunds.restock_by_default` | `true` | Whether the Filament refund form pre-checks its "restock" checkbox for a full refund. Restocking is always an explicit per-refund choice; the automatic late-payment refund never restocks. |
| `admin_email` | `env('SPARKCOMMERCE_ADMIN_EMAIL')` | Store operator address. Receives the new-order mail and every operational payment alert (amount mismatch, dispute, refund failure, failing webhook signatures, reconciliation flags, late-payment auto-refunds, free orders). `null` disables all admin mails; customer mails are unaffected. |
| `table_prefix` | `'sc_'` | Prefix for every package table. |
| `*_table_name` keys | `products`, `product_variations`, `categories`, `product_attributes`, `product_reviews`, `category_product`, `orders`, `anonymous_carts`, `coupons`, `coupon_user`, `coupon_included_products`, `coupon_excluded_products`, `coupon_included_categories`, `coupon_excluded_categories`, `payment_events`, `refunds` | Base names of the package tables (combined with `table_prefix`). |

## Writing a payment gateway driver

Gateways are managed by a Laravel `Manager` bound as the singleton `Rahat1994\SparkCommerce\Payments\PaymentGatewayManager`, aliased to `sparkcommerce.payments`. You add your own driver **without touching the package**.

### The contract

Implement `Rahat1994\SparkCommerce\Payments\Contracts\PaymentGateway`:

```php
interface PaymentGateway
{
    /** Start (or idempotently resume) a payment for the order. */
    public function createPayment(SCOrder $order): PaymentSession;

    /** Cancel the outstanding payment at the processor.
     *  @throws PaymentCancellationRefused when the processor refuses. */
    public function cancelPayment(SCOrder $order): void;

    /** Refund (part of) the captured payment; $idempotencyKey guards retries. */
    public function refund(SCOrder $order, int $amountCents, string $idempotencyKey): RefundResult;

    /** Current processor status: 'succeeded' | 'processing' | 'canceled'
     *  | 'requires_payment_method' | null when unknown. Used by reconciliation. */
    public function retrievePaymentStatus(SCOrder $order): ?string;

    /** Handle an incoming processor notification (raw body + headers). */
    public function handleWebhook(Request $request): Response;
}
```

### PaymentSession flows

`createPayment()` returns a `PaymentSession` with:

- `gateway` — your driver name.
- `flow` — `'client_confirm'` or `'redirect'`.
  - `client_confirm`: the storefront finishes the payment in the browser with the processor's JS SDK (Stripe uses this; `client_params` carries the `client_secret`).
  - `redirect`: the storefront sends the shopper to a processor-hosted page (put the URL in `client_params`).
- `reference` — the processor's payment id (e.g. the PaymentIntent id). Stored on the order as `transaction_id`.
- `clientParams` — whatever the client needs to finish the payment. It is exposed **only** in the checkout response, never stored on the order, never served from any order read endpoint.

### Registering the driver

From any service provider in your app:

```php
use Rahat1994\SparkCommerce\Payments\PaymentGatewayManager;

public function boot(): void
{
    app(PaymentGatewayManager::class)->extend('mollie', fn () => new MollieGateway(
        config('services.mollie'),
    ));

    // or via the alias:
    // app('sparkcommerce.payments')->extend('mollie', fn () => new MollieGateway(...));
}
```

Then set `sparkcommerce.payments.default` to `'mollie'`.

### The webhook entry

Your processor's notifications arrive at the generic route this package registers:

```
POST /sc/webhooks/{gateway}   →  e.g. POST /sc/webhooks/mollie
```

The controller only resolves your driver and calls `handleWebhook()`. An unknown gateway name answers 404.

### Hard invariants a driver must honor

1. **A verified notification is the sole paid authority.** Only a positively verified (signature/shared-secret) success notification may dispatch `HandlePaymentIntentSucceeded`. Nothing else — not a client redirect, not a status poll — may mark an order Paid. `handleWebhook()` must default-deny: anything unverifiable answers 400 without dispatching.
2. **Amount and currency are verified before Paid.** The succeeded handler compares the amount received and currency against the order (`total_amount_cents`, `currency`) and flags a mismatch instead of marking paid. Your webhook payload must carry `data.object.amount_received`, `data.object.currency`, and identify the order (by `data.object.id` matching `transaction_id`, or `data.object.metadata.order_id`).
3. **Cancel-before-expire.** The expiry sweep and the checkout supersede path call `cancelPayment()` **before** they move an order out of `awaiting_payment`. When the processor refuses (payment in flight or already succeeded), throw `PaymentCancellationRefused` — the caller then keeps the order instead of orphaning a charge.
4. **Idempotency by event claim.** Each webhook event needs a stable `id`. Handlers claim `(gateway, event_id)` in the `sc_payment_events` table inside the same transaction as their side effects, so redelivered events are exactly-once. `createPayment()` must also be idempotent per order + total: an unchanged re-checkout returns the **same** session, never a second charge attempt.

## Re-publishing migrations

SparkCommerce's own migration stubs are written to be safe to re-publish and re-run:

- The publisher reuses the existing file in `database/migrations` when one with the same name is already there (no duplicate copies with new timestamps).
- The alter/backfill migrations guard every column, index, and data write, so running them again is a no-op.
- The two data migrations (`backfill_order_statuses`, `add_payment_columns_to_orders`) survey the data first and abort with the offending row ids before writing anything.

**But this is not true for every third-party package.** `binafy/laravel-cart` re-dates its migration files on every `vendor:publish`, so re-publishing creates duplicates and `migrate` then fails on the existing tables. Publish it once (the `sparkcommerce:publish-migrations` command includes it) and do not repeat it.

## Data retention

- **Webhook claim rows** (`sc_payment_events`) are pruned automatically after 30 days by the daily `PrunePaymentEvents` job. Processors stop redelivering long before that (Stripe retries for up to 3 days).
- **Orders snapshot personal data.** Each order stores the customer's shipping/billing addresses and a name/price snapshot of every item at purchase time, so order history does not depend on live product rows. The package never deletes orders. Retention of this personal data (e.g. for GDPR requests) is **your responsibility** as the store operator.

## Known limitations

- **Roles are global.** The `sc_admin` role grants access to all SparkCommerce resources across the whole application. There is no per-store or per-vendor scoping in the base package; that is the multivendor add-on's territory.
- The Stripe driver charges the platform account only — no Connect routing (`transfer_data` / `application_fee`) in this release.

## Upgrading

See [UPGRADE.md](UPGRADE.md) — the checkout flow change is breaking for existing API clients.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Rahat Baksh](https://github.com/rahat1994)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

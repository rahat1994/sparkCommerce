# Upgrade guide — sparkcommerce

This guide covers upgrading a host application from any pre-payment-module version of `rahat1994/sparkcommerce` to `v1.0.0-beta.1`.

The headline: **orders are no longer paid by trust.** An order is created as `awaiting_payment`, a real gateway payment is opened for it, and only a verified gateway webhook marks it `paid`. Unpaid orders now expire. This changes the database schema, adds required background services, and — through the REST API package — **breaks existing storefront clients** (see that package's own UPGRADE.md).

## 1. Schema changes

Publish and run the new migrations:

```bash
php artisan vendor:publish --tag="sparkcommerce-migrations"
php artisan migrate
```

What they do to the `sc_orders` table (expand/contract — nothing is dropped):

- **New money columns in integer cents:** `subtotal_amount`, `discount_amount`, `shipping_fee_amount`, `total_amount_cents`, plus `application_fee_amount` (reserved for the multivendor release, never written by the base package). All nullable; legacy rows are backfilled (`total_amount_cents` = `round(total_amount × 100)`).
- **`currency`** (char 3, nullable) — backfilled with `sparkcommerce.default_currency` and stamped on new orders at write time. The schema itself never hard-codes a currency.
- **`payment_gateway`**, **`expires_at`** — gateway plumbing and the payability window.
- An index on `transaction_id` and a **unique index on `tracking_number`**.
- **The legacy `total_amount` decimal (major units) is kept and never mutated.** New checkouts keep writing it during the deprecation window. It goes away in a later major release.
- `status` / `payment_status` values are backfilled onto the enum values (`awaiting_payment`, `paid`, `processing`, `shipped`, `delivered`, `cancelled`, `expired`, `refunded` / `pending`, `paid`, `failed`, `refunded`, `partially_refunded`). Each converted row keeps its original `(status, payment_status, shipping_status)` triple once under `meta->legacy_status`.

New tables: `sc_payment_events` (webhook claim rows for exactly-once handling) and `sc_refunds`. Other migrations complete the coupon schema, convert `stock_quantity` to an integer, and complete the product-variations table.

### When a migration aborts

`backfill_order_statuses` and `add_payment_columns_to_orders` **survey your data first and abort before writing anything** when they find rows they cannot convert safely. The exception message lists the offending row ids. Cases:

- *NULL or unknown `status` / `payment_status` values* — decide what each offending row really is, update it to a mappable value (see the maps inside the migration stub), and re-run `php artisan migrate`.
- *Negative or non-numeric `total_amount`* — fix the amounts by hand, re-run.
- *Duplicate `tracking_number` values* — customer-held tracking numbers are never rewritten by the migration. Deduplicate them yourself (pick which row keeps the number), then re-run.

Nothing was changed when the migration threw, so re-running after the fix is always safe. All schema changes are guarded (`hasColumn` / `hasIndex`), so a partially applied state cannot double-apply.

## 2. New required services

These did not exist before. Without them the new flow does not work:

- **Queue worker** (`php artisan queue:work`) — every mail notification and every webhook payment job is queued. No worker means no order is ever marked paid.
- **Scheduler** (cron `schedule:run` or `schedule:work`) — runs `ReconcileStuckPayments` (every 30 min), `PrunePaymentEvents` (daily), and the REST package's `ExpireStaleOrders` (every 5 min). No scheduler means unpaid orders never expire and their stock stays locked forever.

## 3. Roles and the panel gate

Every SparkCommerce Filament resource is now behind the `access-sparkcommerce-admin` gate:

- A user passes when they hold the role in `sparkcommerce.admin_role` (default `sc_admin`), **or** when you set `sparkcommerce.panel_gate` to an invokable class that returns true for them.
- A user model without roles support (`hasRole()`) is **denied by default**.

After upgrading, run:

```bash
php artisan sc:publish-roles
```

and assign `sc_admin` to every user who should keep admin access — existing admins are locked out of the SparkCommerce resources until you do.

## 4. Config additions

Re-publish (or diff) the config file:

```bash
php artisan vendor:publish --tag="sparkcommerce-config" --force   # or merge by hand
```

New keys since the last release: `vendor_model`, `admin_role`, `panel_gate`, `vendor_owner_role`, `default_currency`, `allow_free_orders`, `order_ttl`, `max_open_orders`, `payments` (gateway block), `refunds.restock_by_default`, `admin_email`, `payment_events_table_name`, `refunds_table_name`. See the README's configuration reference for what each does.

Set `SPARKCOMMERCE_ADMIN_EMAIL` if you want the new-order mail and the operational payment alerts (amount mismatch, disputes, refund failures, failing webhook signatures, reconciliation flags, late-payment auto-refunds, free orders). Leaving it unset silently disables all admin mails.

## 5. Stripe setup

The payment module defaults to Stripe. You must:

1. Set `SPARKCOMMERCE_STRIPE_SECRET_KEY` and `SPARKCOMMERCE_STRIPE_WEBHOOK_SECRET`.
2. Register a webhook endpoint at `POST /sc/webhooks/stripe` subscribed to exactly: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`, `refund.failed`, `charge.dispute.created`.

Without the webhook secret every notification is rejected (default-deny) and no order is ever marked paid. See the README's Stripe section for the local Stripe CLI recipe.

## 6. Removed classes and behavior

- The empty scaffolding class `Rahat1994\SparkCommerce\SparkCommerce` and the facade `Rahat1994\SparkCommerce\Facades\SparkCommerce` **no longer exist**. Drop any imports; they never did anything.
- The "Coming Soon" placeholder tabs on the product form are gone.
- **Order status writes go through the state machine.** `status` must only change via `Rahat1994\SparkCommerce\Services\OrderTransitionService::transition()`; illegal moves throw `IllegalOrderTransition`. If your customizations wrote `$order->status = ...` directly, route them through the service.
- Order creation is not a transition: new orders start directly in `awaiting_payment`.

## 7. Money handling for customizations

The order's canonical amounts are now **integer cents** (`total_amount_cents` and friends, cast to `Cknow\Money\Money` on the model). Read raw cents with `$order->getRawOriginal('total_amount_cents')`. Do not derive amounts from the legacy `total_amount` column in new code — it is on the deprecation clock.

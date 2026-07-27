# Changelog

All notable changes to `sparkCommerce` will be documented in this file.

## v1.0.0-beta.1 - Unreleased

First public beta of the reworked base package. The theme of this release: **orders are only paid when the payment processor says so.** See [UPGRADE.md](UPGRADE.md) — the flow change is breaking for API clients, and the schema migrations rework the orders table.

### Added

- **Payment gateway module.** A `PaymentGateway` contract (create/cancel/refund/status/webhook), a `PaymentGatewayManager` (singleton, alias `sparkcommerce.payments`) with an `extend()` seam so apps can register their own drivers, a shipped **Stripe driver** (PaymentIntents on the platform account, pinned API version, idempotent per order + total), and a scriptable `FakeGateway` for tests.
- **Generic webhook endpoint** `POST /sc/webhooks/{gateway}` (no CSRF/session middleware). Drivers verify signatures themselves and deny by default; the Stripe driver handles `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`, `refund.failed`, and `charge.dispute.created`, and alerts when signature verification keeps failing.
- **Order lifecycle state machine.** `OrderStatus` / `PaymentStatus` enums, `OrderTransitionService` with transition events, and a data backfill that maps historical rows onto the enums (original values preserved in `meta->legacy_status`). Orders start in `awaiting_payment`; only a **verified** succeeded-webhook — with the amount and currency checked against the order — marks them `paid`.
- **Exactly-once webhook handling** via the `sc_payment_events` claim table (unique per gateway + event id, claimed in the same transaction as the side effects). Claims are pruned after 30 days by a daily job.
- **Payment reconciliation job** (every 30 minutes): overdue unpaid orders are checked against their gateway; a payment that actually succeeded is flagged and announced (`PaymentNeedsReconciliation`) — never silently marked paid.
- **Refunds and disputes.** `sc_refunds` table, refund service with per-refund idempotency keys, a Filament refund action (optional restock), dispute handling, and an automatic refund for payments that land on already-expired orders (never restocks).
- **Queued transactional mail.** Customer order confirmation and refund mails, a new-order mail to the store operator, backorder notices, and one parameterized admin alert covering the operational payment events (amount mismatch, dispute, refund failure, failing webhook signatures, reconciliation flags, late-payment auto-refunds, free orders). All notifications are queued and sent after commit. Controlled by the new `admin_email` config.
- **Inventory enforcement.** Atomic stock reservation at checkout, automatic release when unpaid orders are cancelled or expire, and a backorder policy per product.
- **Coupon schema completion** and atomic single-use redemption primitives (claim at checkout, usage recorded at paid, release on cancel/expiry).
- **Money canonicalization.** Integer-cent columns (`subtotal_amount`, `discount_amount`, `shipping_fee_amount`, `total_amount_cents`) plus a `currency` code on orders, backfilled from the legacy `total_amount`. The new migrations survey existing data first and abort — before writing anything — listing offending rows.
- **Admin access control.** The `access-sparkcommerce-admin` gate (role from `admin_role`, overridable via `panel_gate`), and the `sc:publish-roles` command that creates the `sc_admin` role.
- **Config keys:** `vendor_model`, `admin_role`, `panel_gate`, `vendor_owner_role`, `default_currency`, `allow_free_orders`, `order_ttl`, `max_open_orders`, `payments` (driver + gateway credentials), `refunds.restock_by_default`, `admin_email`, `payment_events_table_name`, `refunds_table_name`.
- **Test harness:** testbench 11 suite with model factories and a Filament panel fixture; CI revived on the Laravel 13 matrix.

### Changed

- Requires **PHP ^8.3, Laravel 13, Filament ~5.0**.
- Orders created through checkout begin in `awaiting_payment` and **expire** after `order_ttl` minutes if unpaid (stock and coupon reservations released).
- Every order status change must go through `OrderTransitionService`; illegal transitions throw.
- The base package now runs fully **standalone** — no reference to the multivendor packages remains; the vendor model is a config seam.
- A queue worker and the scheduler are now required runtime services.

### Fixed

- Coupon validation read a non-existent date column, so every coupon passed the date check; it now reads `end_date`.
- `SCProductVariation` pointed at the products table instead of the variations table; variation persistence is wired through the product pages, and a guarded corrective migration completes the variations schema.
- Model fillable/cast drift on `SCProduct` and `SCCoupon`.
- `FakeGateway` returns a distinct reference per refund, matching real gateway behavior against the unique refund-reference index.

### Removed

- The empty scaffolding class `Rahat1994\SparkCommerce\SparkCommerce` and its facade `Rahat1994\SparkCommerce\Facades\SparkCommerce`.
- The "Coming Soon" placeholder tabs on the product form.

## 1.0.0 - 202X-XX-XX

- initial release

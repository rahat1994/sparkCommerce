<?php

// config for Rahat1994/SparkCommerce
return [
    'decimal_value' => 100,

    /*
     * Fully qualified class name of the vendor (shop) model.
     * Null in the standalone package; set by the multivendor package.
     */
    'vendor_model' => null,

    /*
     * Name of the role that grants access to the SparkCommerce admin
     * resources. The role is created by the `sc:publish-roles` command.
     */
    'admin_role' => 'sc_admin',

    /*
     * Optional invokable class-string that decides panel access. When set,
     * it is resolved from the container and called with the current user,
     * overriding the default `admin_role` check.
     */
    'panel_gate' => null,

    /*
     * Name of the role that owns vendors (shops).
     * Null in the standalone package; set by the multivendor package.
     */
    'vendor_owner_role' => null,

    /*
     * ISO 4217 currency code stamped onto new orders at write time
     * (checkout and the payment-columns backfill). The schema itself never
     * hard-codes a currency default.
     */
    'default_currency' => 'USD',

    /*
     * Whether a checkout whose grand total is zero (after discounts and
     * shipping) may complete without a payment. When true, such orders are
     * created as usual and immediately transitioned to Paid, and a
     * FreeOrderPlaced event is dispatched. When false, zero-total checkouts
     * are rejected with a validation error.
     */
    'allow_free_orders' => true,

    /*
     * Minutes an awaiting_payment order stays payable before the expiry
     * sweep transitions it to Expired (releasing its stock and coupon
     * reservations). The TTL is deliberately short: an unpaid order locks
     * up reserved stock, keeps a window open for card-testing abuse, and
     * must stay well inside the 24h horizon Stripe keeps idempotency keys
     * around for, so a retried payment can never target a vanished order.
     */
    'order_ttl' => 90,

    /*
     * Maximum number of awaiting_payment orders one user may hold open at
     * the same time. Checkout rejects with a validation error beyond this
     * cap. It bounds how much stock a single user can lock up through
     * repeated checkouts before paying for any of them.
     */
    'max_open_orders' => 5,

    /*
     * Payment gateway module (R13). `default` names the driver checkout
     * uses; 'stripe' ships with the package and 'fake' backs the test
     * suites. Adopters register additional drivers from their own service
     * providers: app('sparkcommerce.payments')->extend('name', fn () => ...).
     */
    'payments' => [
        'default' => 'stripe',

        'gateways' => [
            'stripe' => [
                'secret_key' => env('SPARKCOMMERCE_STRIPE_SECRET_KEY'),
                'webhook_secret' => env('SPARKCOMMERCE_STRIPE_WEBHOOK_SECRET'),
            ],

            /*
             * The fake driver's shared webhook secret is only ever set by
             * test environments; without it every fake webhook is denied.
             */
            'fake' => [
                'webhook_secret' => env('SPARKCOMMERCE_FAKE_WEBHOOK_SECRET'),
            ],
        ],
    ],

    'table_prefix' => 'sc_',
    'products_table_name' => 'products',
    'product_variants_table_name' => 'product_variations',
    'categories_table_name' => 'categories',
    'product_attributes_table_name' => 'product_attributes',
    'product_reviews_table_name' => 'product_reviews',
    'category_product_table_name' => 'category_product',
    'orders_table_name' => 'orders',
    'anonymous_carts_table_name' => 'anonymous_carts',
    'coupons_table_name' => 'coupons',
    'coupon_user_table_name' => 'coupon_user',
    'coupon_included_products_table_name' => 'coupon_included_products',
    'coupon_excluded_products_table_name' => 'coupon_excluded_products',
    'coupon_included_categories_table_name' => 'coupon_included_categories',
    'coupon_excluded_categories_table_name' => 'coupon_excluded_categories',
    'payment_events_table_name' => 'payment_events',
];

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
];

<?php

// config for Rahat1994/SparkCommerce
return [
    'decimal_value' => 100,
    'vendor_model' => Rahat1994\SparkCommerceMultiVendor\Models\SCMVVendor::class,
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

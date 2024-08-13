<?php

// translations for Rahat1994/SparkCommerce
return [
    'resource' => [
        'product' => [
            'model_label' => 'Product',
            'icon' => 'heroicon-o-rectangle-stack',
            'model_plural_label' => 'Products',
            'navigation_group' => 'Products',
            'navigation' => 'All Products',
            'creation_form' => [
                'product_name' => 'Product Name',
                'description' => 'Description',
                'sku' => 'SKU',
                'regular_price' => 'Regular Price',
                'sale_price' => 'Sale Price',
                'product_dimension' => [
                    'fieldset_name' => 'Product Dimensions',
                    'height' => 'Height',
                    'weight' => 'Weight',
                    'width' => 'Width',
                    'length' => 'Length',
                ],
                'tabs_section' => [
                    'tabs' => [
                        'general' => 'General',
                        'inventory' => 'Inventory',
                        'linked_products' => 'Linked Products',
                        'pricing' => 'Pricing',
                        'shipping' => 'Shipping',
                        'product_dimension' => 'Product Dimensions',
                        'attributes' => 'Attributes',
                        'variations' => 'Variations',
                        'advanced' => 'Advanced',
                        'more_option' => 'More Options',
                    ],

                ],

            ],
        ],
        'category' => [
            'model_label' => 'Category',
            'model_plural_label' => 'Categories',
            'navigation_group' => 'Products',
            'navigation' => 'Categories',
            'creation_form' => [
                'name' => 'Name',
                'description' => 'Description',
                'parent_category' => 'Parent Category',
            ],
        ],
        'tag' => [
            'model_label' => 'Tag',
            'model_plural_label' => 'Tags',
            'navigation_group' => 'Products',
            'navigation' => 'Tags',
            'creation_form' => [
                'name' => 'Name',
                'description' => 'Description',
            ],
        ],
        'review' => [
            'model_label' => 'Review',
            'model_plural_label' => 'Reviews',
            'navigation_group' => 'Products',
            'navigation' => 'Reviews',
            'creation_form' => [
                'name' => 'Name',
                'description' => 'Description',
            ],
        ],
        'order' => [
            'model_label' => 'Order',
            'model_plural_label' => 'Orders',
            'navigation_group' => 'Sparkcommerce',
            'navigation' => 'orders',
        ],
        'coupon' => [
            'model_label' => 'Coupon',
            'model_plural_label' => 'Coupons',
            'navigation_group' => 'Sparkcommerce',
            'navigation' => 'coupons',
            'creation_form' => [
                'name' => 'Coupon Code',
                'description' => 'Description',
                'coupon_data' => 'Coupon Data',
            ],
        ],
        'user' => [
            'model_label' => 'User',
            'model_plural_label' => 'Users',
            'navigation_group' => 'Administration',
            'navigation' => 'Users',
            'creation_form' => [
                'name' => 'Name',
                'email' => 'Email',
                'password' => 'Password',
                'password_confirmation' => 'Confirm Password',
                'meta' => 'Meta',
                'role' => 'Role',
            ],
        ],
    ],
];

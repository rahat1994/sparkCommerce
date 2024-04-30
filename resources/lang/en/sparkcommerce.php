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
                'product_dimension' => [
                    'fieldset_name' => 'Product Dimensions',
                    'height' => 'Height',
                    'weight' => 'Weight',
                    'width' => 'Width',
                    'length' => 'Length',
                ],
                'tabs_section' => [
                    'tabs' => [
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
    ],
];

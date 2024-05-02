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
    ],
];

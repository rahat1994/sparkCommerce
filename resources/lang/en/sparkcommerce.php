<?php

// translations for Rahat1994/SparkCommerce
return [
    'mail' => [
        'order_confirmation' => [
            'subject' => 'Order :order_number is confirmed',
            'body' => 'Thank you for your order! We have received your payment for order :order_number.',
            'total' => 'Order total: :amount.',
            'outro' => 'We will let you know as soon as your order ships.',
        ],
        'new_order_received' => [
            'subject' => 'New paid order :order_number',
            'body' => 'A new order :order_number has been paid and is ready to process.',
            'total' => 'Order total: :amount.',
            'customer' => 'Customer: :email.',
            'outro' => 'Sign in to the admin panel to process it.',
        ],
        'order_refunded' => [
            'subject' => 'Your refund for order :order_number',
            'body' => 'A refund of :amount has been issued for your order :order_number.',
            'outro' => 'Depending on your bank, it can take a few days to appear on your statement.',
        ],
        'backorder' => [
            'subject' => ':product is on backorder',
            'body' => 'You ordered :quantity x :product, which is currently out of stock. The missing units are on backorder.',
            'outro' => 'We will ship them to you as soon as they are back in stock.',
        ],
        'admin_alert' => [
            'amount_mismatch' => [
                'subject' => 'Payment amount mismatch on order :order_number',
                'body' => 'Order :order_number received a payment of :amount :currency but expected :expected :currency. The order was flagged and NOT transitioned — please review it.',
            ],
            'late_payment_refunded' => [
                'subject' => 'Late payment on expired order :order_number refunded',
                'body' => 'Order :order_number was paid after it had already expired. The payment of :amount :currency was refunded automatically; the order stays expired.',
            ],
            'dispute_created' => [
                'subject' => 'Dispute opened against order :order_number',
                'body' => 'The payment processor opened a dispute (chargeback) against the charge of order :order_number. Please respond to it in the processor dashboard.',
            ],
            'refund_failed' => [
                'subject' => 'Refund failed for order :order_number',
                'body' => 'A refund of :amount :currency for order :order_number failed: :failure_reason. The money has NOT been returned — please retry or resolve it with the processor.',
            ],
            'webhook_signature_failing' => [
                'subject' => 'Webhook signature failures on the :gateway gateway',
                'body' => 'The :gateway webhook signature failed verification :failures times within the last hour. Payment notifications are being dropped — check the configured webhook secret.',
            ],
            'needs_reconciliation' => [
                'subject' => 'Order :order_number needs payment reconciliation',
                'body' => 'Order :order_number expired, but its gateway payment actually succeeded — the paid webhook was never processed. Please review and reconcile it.',
            ],
            'free_order_placed' => [
                'subject' => 'Free order :order_number placed',
                'body' => 'Order :order_number completed with a zero total (after discounts and shipping) and was marked paid without a payment.',
            ],
            'job_failed' => [
                'subject' => 'A payment job failed: :job',
                'body' => 'The :job payment job failed after exhausting its retries (:exception). Please check the queue worker and reconcile any affected payment.',
            ],
        ],
    ],
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
                'coupon_type' => 'Coupon Type',
                'coupon_amount' => 'Coupon Amount',
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

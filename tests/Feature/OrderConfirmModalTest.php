<?php

use Rahat1994\SparkCommerce\Models\SCOrder;

/**
 * Render the order-confirmation modal for an order whose stored discount is
 * the ARRAY shape that product-specific coupons produce (calculateDiscount
 * returns `['discount' => X, 'discount_breakdown' => [...]]`). The modal used
 * to pass that array straight to number_format() and crash.
 */
function renderOrderConfirmModal(SCOrder $order): string
{
    return view('sparkcommerce::actions.order-confirm-modal', [
        'order' => $order,
        'orderContent' => ['items' => []],
        'currency' => '$',
    ])->render();
}

it('renders the modal for a product-specific coupon whose discount is an array', function () {
    $order = SCOrder::factory()->create([
        'currency' => 'USD',
        'total_amount_cents' => 4500,
        'user_id' => null,
        'discount' => [
            'coupon_id' => null,
            'coupon_code' => 'PROD10',
            // Product-specific coupons store the whole calculateDiscount
            // array here — the shape that crashed number_format().
            'discount' => [
                'discount' => 5.0,
                'discount_breakdown' => [
                    ['slug' => 'widget', 'product_total_amount' => 50.0, 'product_discount' => 5.0],
                ],
            ],
            'amount_cents' => 500,
            'reserved' => false,
            'total_amount' => 45.0,
        ],
    ]);

    $html = renderOrderConfirmModal($order);

    // No TypeError: the discount is read from the integer-cents field
    // (500 / 100 = 5.00) instead of the array.
    expect($html)->toContain('PROD10')
        ->and($html)->toContain('5.00');
});

it('still renders the modal for a plain numeric cart-wide discount', function () {
    $order = SCOrder::factory()->create([
        'currency' => 'USD',
        'total_amount_cents' => 4500,
        'user_id' => null,
        'discount' => [
            'coupon_id' => null,
            'coupon_code' => 'CART10',
            'discount' => 5.0,
            'amount_cents' => 500,
            'reserved' => false,
            'total_amount' => 45.0,
        ],
    ]);

    $html = renderOrderConfirmModal($order);

    expect($html)->toContain('CART10')
        ->and($html)->toContain('5.00');
});

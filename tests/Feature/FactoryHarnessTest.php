<?php

use Rahat1994\SparkCommerce\Models\SCCategory;
use Rahat1994\SparkCommerce\Models\SCCoupon;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCProduct;
use Rahat1994\SparkCommerce\Models\SCReview;

it('persists a product with categories via factories', function () {
    $product = SCProduct::factory()->create();
    $categories = SCCategory::factory()->count(2)->create();

    $product->categories()->attach($categories->pluck('id'));

    expect($product->fresh()->categories)->toHaveCount(2);
});

it('creates every base model via its factory', function () {
    expect(SCProduct::factory()->create())->toBeInstanceOf(SCProduct::class)
        ->and(SCCategory::factory()->create())->toBeInstanceOf(SCCategory::class)
        ->and(SCOrder::factory()->awaitingPayment()->create())
        ->toBeInstanceOf(SCOrder::class)
        ->status->toBe('awaiting_payment')
        ->and(SCCoupon::factory()->expired()->create())->toBeInstanceOf(SCCoupon::class)
        ->and(SCReview::factory()->create())->toBeInstanceOf(SCReview::class);
});

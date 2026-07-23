<?php

use Rahat1994\SparkCommerce\Models\SCCoupon;

it('is invalid when the end date has passed', function () {
    $coupon = SCCoupon::factory()->create([
        'end_date' => now()->subDay(),
    ]);

    expect($coupon->isValid())->toBeFalse();
});

it('is valid when the end date is in the future', function () {
    $coupon = SCCoupon::factory()->create([
        'end_date' => now()->addDay(),
    ]);

    expect($coupon->isValid())->toBeTrue();
});

it('is valid when there is no end date', function () {
    $coupon = SCCoupon::factory()->create([
        'end_date' => null,
    ]);

    expect($coupon->isValid())->toBeTrue();
});

it('resolves the users pivot to the configured table name', function () {
    $expectedTable = config('sparkcommerce.table_prefix') . config('sparkcommerce.coupon_user_table_name');

    expect((new SCCoupon)->users()->getTable())->toBe($expectedTable)
        ->and($expectedTable)->toBe('sc_coupon_user');
});

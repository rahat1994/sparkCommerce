<?php

use Illuminate\Support\Facades\DB;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Models\SCCoupon;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Services\OrderTransitionService;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;

function couponModelUser(): User
{
    return User::create([
        'name' => 'Coupon User',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('secret-password'),
    ]);
}

function couponUserPivotTable(): string
{
    return config('sparkcommerce.table_prefix') . config('sparkcommerce.coupon_user_table_name');
}

/**
 * An order shaped like the checkout writes it: the applied-coupon info
 * lives in the `discount` json, including whether a single-use slot was
 * reserved at checkout time.
 */
function couponModelOrderFor(User $user, SCCoupon $coupon, bool $reserved): SCOrder
{
    return SCOrder::factory()->awaitingPayment()->create([
        'user_id' => $user->id,
        'discount' => [
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->coupon_code,
            'amount_cents' => 500,
            'reserved' => $reserved,
        ],
    ]);
}

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

it('casts the converted limit columns to integers', function () {
    $coupon = SCCoupon::factory()->create([
        'usage_limit' => '5',
        'usage_limit_per_user' => '2',
    ])->fresh();

    expect($coupon->usage_limit)->toBe(5)
        ->and($coupon->usage_limit_per_user)->toBe(2)
        ->and($coupon->usage_count)->toBe(0);
});

it('reads spend limits in cents, preferring the cents columns over legacy strings', function () {
    $centsBacked = SCCoupon::factory()->create(['min_spend_cents' => 1234, 'max_spend_cents' => 5678]);

    expect($centsBacked->minSpendCents())->toBe(1234)
        ->and($centsBacked->maxSpendCents())->toBe(5678);

    // Legacy string columns (major units) are the fallback for rows the
    // backfill has not touched, e.g. coupons written by the admin form.
    $legacyBacked = SCCoupon::factory()->create(['min_spend' => '50.5', 'max_spend' => '100']);
    $legacyBacked->min_spend_cents = null;
    $legacyBacked->max_spend_cents = null;

    expect($legacyBacked->minSpendCents())->toBe(5050)
        ->and($legacyBacked->maxSpendCents())->toBe(10000);

    $unlimited = SCCoupon::factory()->create();

    expect($unlimited->minSpendCents())->toBeNull()
        ->and($unlimited->maxSpendCents())->toBeNull();
});

it('reports remaining global uses from usage_count and usage_limit', function () {
    expect(SCCoupon::factory()->create()->hasRemainingGlobalUses())->toBeTrue()
        ->and(SCCoupon::factory()->create(['usage_limit' => 2, 'usage_count' => 1])->hasRemainingGlobalUses())->toBeTrue()
        ->and(SCCoupon::factory()->create(['usage_limit' => 2, 'usage_count' => 2])->hasRemainingGlobalUses())->toBeFalse();
});

it('lets exactly usage_limit claims through', function () {
    $coupon = SCCoupon::factory()->create(['usage_limit' => 2]);

    $results = [
        $coupon->claimSingleUse(),
        $coupon->claimSingleUse(),
        $coupon->claimSingleUse(),
    ];

    expect($results)->toBe([true, true, false])
        ->and($coupon->fresh()->usage_count)->toBe(2);
});

it('claims nothing when the coupon has no usage limit', function () {
    $coupon = SCCoupon::factory()->create(['usage_limit' => null]);

    expect($coupon->claimSingleUse())->toBeTrue()
        ->and($coupon->fresh()->usage_count)->toBe(0);
});

it('releases a claimed use and floors the counter at zero', function () {
    $coupon = SCCoupon::factory()->create(['usage_limit' => 1]);

    $coupon->claimSingleUse();
    $coupon->releaseSingleUse();

    expect($coupon->fresh()->usage_count)->toBe(0);

    // A second release must not push the counter below zero.
    $coupon->releaseSingleUse();

    expect($coupon->fresh()->usage_count)->toBe(0);
});

it('records usage per user and increments the pivot on repeats', function () {
    $user = couponModelUser();
    $coupon = SCCoupon::factory()->create();
    $order = couponModelOrderFor($user, $coupon, reserved: false);

    $coupon->recordUsageFor($order);
    $coupon->recordUsageFor($order);

    $pivotRows = DB::table(couponUserPivotTable())
        ->where('coupon_id', $coupon->id)
        ->where('user_id', $user->id)
        ->get();

    expect($pivotRows)->toHaveCount(1)
        ->and((int) $pivotRows->first()->usage_count)->toBe(2)
        ->and($pivotRows->first()->used_at)->not->toBeNull()
        ->and($coupon->usesByUser($user))->toBe(2)
        // Un-reserved coupons count globally at record time.
        ->and($coupon->fresh()->usage_count)->toBe(2);
});

it('does not double count the global use of a coupon reserved at checkout', function () {
    $user = couponModelUser();
    $coupon = SCCoupon::factory()->create(['usage_limit' => 1]);

    // Checkout already claimed the single-use slot...
    expect($coupon->claimSingleUse())->toBeTrue();

    // ...so recording the paid usage only writes the pivot.
    $order = couponModelOrderFor($user, $coupon, reserved: true);
    $coupon->recordUsageFor($order);

    expect($coupon->usesByUser($user))->toBe(1)
        ->and($coupon->fresh()->usage_count)->toBe(1);
});

it('releases a reserved single-use slot when the awaiting_payment order is cancelled', function () {
    $user = couponModelUser();
    $coupon = SCCoupon::factory()->create(['usage_limit' => 1]);
    $coupon->claimSingleUse();

    $order = couponModelOrderFor($user, $coupon, reserved: true);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Cancelled);

    expect($coupon->fresh()->usage_count)->toBe(0);
});

it('does not release a coupon the checkout never reserved', function () {
    $user = couponModelUser();
    $coupon = SCCoupon::factory()->create(['usage_limit' => 5, 'usage_count' => 3]);

    $order = couponModelOrderFor($user, $coupon, reserved: false);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Expired);

    expect($coupon->fresh()->usage_count)->toBe(3);
});

it('skips the release without throwing when the reserved coupon no longer exists', function () {
    $user = couponModelUser();
    $coupon = SCCoupon::factory()->create(['usage_limit' => 1]);
    $order = couponModelOrderFor($user, $coupon, reserved: true);

    $coupon->delete();

    app(OrderTransitionService::class)->transition($order, OrderStatus::Cancelled);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function couponSchemaTable(): string
{
    return strval(config('sparkcommerce.table_prefix')) . strval(config('sparkcommerce.coupons_table_name'));
}

/**
 * Rebuild the coupons table in its LEGACY shape (string limit/spend columns,
 * no usage_count, no unique coupon_code index) so the completion stub has
 * something real to convert. The provider's migration list already ran the
 * completion during test setup, so the live table is post-conversion by the
 * time a test starts.
 */
function rebuildLegacyCouponsTable(): void
{
    Schema::dropIfExists(couponSchemaTable());

    (include __DIR__ . '/../../database/migrations/create_sc_coupons_table.php.stub')->up();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function insertLegacyCouponRow(array $attributes = []): int
{
    return DB::table(couponSchemaTable())->insertGetId(array_merge([
        'coupon_code' => strtoupper(fake()->unique()->bothify('LEGACY##??')),
        'coupon_type' => 'fixed_cart_discount',
        'coupon_amount' => '500',
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function runCouponSchemaCompletion(): void
{
    (include __DIR__ . '/../../database/migrations/complete_coupon_schema.php.stub')->up();
}

/**
 * @return array<int, array<string, mixed>>
 */
function couponTableSnapshot(): array
{
    return DB::table(couponSchemaTable())->orderBy('id')->get()
        ->map(fn (object $row): array => (array) $row)
        ->all();
}

it('aborts listing the duplicate coupon codes without writing anything', function () {
    rebuildLegacyCouponsTable();

    insertLegacyCouponRow(['coupon_code' => 'DUP10']);
    insertLegacyCouponRow(['coupon_code' => 'DUP10']);

    expect(fn () => runCouponSchemaCompletion())
        ->toThrow(RuntimeException::class, 'DUP10');

    // Nothing was written: the completion never got past the survey.
    expect(Schema::hasColumn(couponSchemaTable(), 'usage_count'))->toBeFalse()
        ->and(Schema::hasColumn(couponSchemaTable(), 'min_spend_cents'))->toBeFalse();
});

it('aborts listing rows whose usage_limit is not a whole number', function () {
    rebuildLegacyCouponsTable();

    insertLegacyCouponRow(['usage_limit' => '10']);
    $offenderId = insertLegacyCouponRow(['usage_limit' => 'often']);

    expect(fn () => runCouponSchemaCompletion())
        ->toThrow(RuntimeException::class, (string) $offenderId);

    expect(Schema::hasColumn(couponSchemaTable(), 'usage_count'))->toBeFalse();
});

it('converts a clean legacy coupons table', function () {
    rebuildLegacyCouponsTable();

    $fullId = insertLegacyCouponRow([
        'coupon_code' => 'FULL',
        'usage_limit' => '10',
        'usage_limit_per_user' => '',
        'min_spend' => '50.50',
        'max_spend' => null,
        // Float noise from the legacy x100 model event.
        'coupon_amount' => '1999.0000000000002',
    ]);
    $sparseId = insertLegacyCouponRow([
        'coupon_code' => 'SPARSE',
        'usage_limit' => null,
        'min_spend' => '',
        'max_spend' => '100',
        'coupon_amount' => '500',
    ]);

    runCouponSchemaCompletion();

    $rows = DB::table(couponSchemaTable())->get()->keyBy('id');

    expect(Schema::hasColumn(couponSchemaTable(), 'usage_count'))->toBeTrue()
        ->and(Schema::hasIndex(couponSchemaTable(), ['coupon_code'], 'unique'))->toBeTrue()
        ->and((int) $rows[$fullId]->usage_count)->toBe(0)
        ->and((int) $rows[$fullId]->usage_limit)->toBe(10)
        // Empty string means "no limit" and becomes NULL.
        ->and($rows[$fullId]->usage_limit_per_user)->toBeNull()
        ->and((int) $rows[$fullId]->min_spend_cents)->toBe(5050)
        ->and($rows[$fullId]->max_spend_cents)->toBeNull()
        ->and((int) $rows[$fullId]->coupon_amount)->toBe(1999)
        // Legacy spend columns stay untouched (expand/contract).
        ->and((string) $rows[$fullId]->min_spend)->toBe('50.50')
        ->and($rows[$sparseId]->usage_limit)->toBeNull()
        ->and($rows[$sparseId]->min_spend_cents)->toBeNull()
        ->and((int) $rows[$sparseId]->max_spend_cents)->toBe(10000)
        ->and((int) $rows[$sparseId]->coupon_amount)->toBe(500)
        ->and((string) $rows[$sparseId]->max_spend)->toBe('100');
});

it('rejects duplicate coupon codes once the unique index exists', function () {
    rebuildLegacyCouponsTable();
    insertLegacyCouponRow(['coupon_code' => 'ONCE']);

    runCouponSchemaCompletion();

    expect(fn () => insertLegacyCouponRow(['coupon_code' => 'ONCE']))
        ->toThrow(QueryException::class);
});

it('is a no-op when run a second time', function () {
    rebuildLegacyCouponsTable();

    insertLegacyCouponRow([
        'coupon_code' => 'TWICE',
        'usage_limit' => '3',
        'min_spend' => '10',
        'coupon_amount' => '250',
    ]);

    runCouponSchemaCompletion();

    $snapshot = couponTableSnapshot();

    runCouponSchemaCompletion();

    expect(couponTableSnapshot())->toEqual($snapshot);
});

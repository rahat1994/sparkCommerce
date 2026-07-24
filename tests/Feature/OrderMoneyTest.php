<?php

use Cknow\Money\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Rahat1994\SparkCommerce\Models\SCOrder;

function moneyOrdersTable(): string
{
    return (new SCOrder)->getTable();
}

/**
 * Insert a legacy-shaped order row straight through the query builder so the
 * new money columns stay untouched (NULL), exactly like a pre-upgrade row.
 *
 * @param  array<string, mixed>  $attributes
 */
function insertLegacyMoneyOrderRow(array $attributes = []): int
{
    return DB::table(moneyOrdersTable())->insertGetId(array_merge([
        'items' => json_encode([]),
        'total_amount' => 100,
        'tracking_number' => (string) Str::ulid(),
        'order_number' => 'ORD-' . fake()->unique()->numerify('######'),
        'status' => 'awaiting_payment',
        'payment_status' => null,
        'shipping_status' => null,
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function runPaymentColumnsMigration(): void
{
    (include __DIR__ . '/../../database/migrations/add_payment_columns_to_orders.php.stub')->up();
}

it('creates the payment columns and indexes on a fresh install', function () {
    $table = moneyOrdersTable();

    expect(Schema::hasColumns($table, [
        'currency',
        'subtotal_amount',
        'discount_amount',
        'shipping_fee_amount',
        'application_fee_amount',
        'total_amount_cents',
        'payment_gateway',
        'expires_at',
    ]))->toBeTrue()
        ->and(Schema::hasIndex($table, ['tracking_number'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex($table, ['transaction_id']))->toBeTrue();
});

it('renders the cents columns as money through the casts', function () {
    $order = SCOrder::factory()->create([
        'currency' => 'USD',
        'subtotal_amount' => 1999,
        'total_amount_cents' => 1999,
        'expires_at' => '2026-01-01 00:00:00',
    ]);

    $order->refresh();

    expect($order->total_amount_cents)->toBeInstanceOf(Money::class)
        ->and($order->total_amount_cents->formatByDecimal())->toBe('19.99')
        ->and((int) $order->getRawOriginal('total_amount_cents'))->toBe(1999)
        ->and($order->subtotal_amount)->toBeInstanceOf(Money::class)
        ->and((int) $order->subtotal_amount->getAmount())->toBe(1999)
        ->and($order->expires_at)->toBeInstanceOf(Carbon::class)
        ->and($order->discount_amount)->toBeNull();
});

it('backfills total_amount_cents and currency from the legacy total_amount column', function () {
    $id = insertLegacyMoneyOrderRow([
        'total_amount' => 19.99,
        'tracking_number' => 'LEGACY-TRACK-1',
        'currency' => null,
        'total_amount_cents' => null,
    ]);

    runPaymentColumnsMigration();

    $row = DB::table(moneyOrdersTable())->find($id);

    expect((int) $row->total_amount_cents)->toBe(1999)
        ->and($row->currency)->toBe('USD')
        ->and(round((float) $row->total_amount, 2))->toBe(19.99)
        ->and($row->tracking_number)->toBe('LEGACY-TRACK-1');
});

it('does not rewrite rows that already have cents on a second run', function () {
    $id = insertLegacyMoneyOrderRow(['total_amount' => 19.99]);

    runPaymentColumnsMigration();

    // Change the legacy column afterwards: a second run must NOT re-derive
    // the cents value, because only NULL cents rows are ever filled.
    DB::table(moneyOrdersTable())->where('id', $id)->update(['total_amount' => 100]);

    runPaymentColumnsMigration();

    $row = DB::table(moneyOrdersTable())->find($id);

    expect((int) $row->total_amount_cents)->toBe(1999)
        ->and($row->currency)->toBe('USD');
});

it('aborts the cents backfill when a legacy total_amount is negative, changing nothing', function () {
    $cleanId = insertLegacyMoneyOrderRow(['total_amount' => 19.99]);
    $offenderId = insertLegacyMoneyOrderRow(['total_amount' => -5]);

    expect(fn () => runPaymentColumnsMigration())
        ->toThrow(RuntimeException::class, (string) $offenderId);

    $clean = DB::table(moneyOrdersTable())->find($cleanId);

    expect($clean->total_amount_cents)->toBeNull()
        ->and($clean->currency)->toBeNull();
});

it('aborts before creating the tracking number unique index when duplicates exist', function () {
    $table = moneyOrdersTable();

    // Simulate a legacy database: the unique index does not exist yet.
    Schema::table($table, function ($blueprint) {
        $blueprint->dropUnique(['tracking_number']);
    });

    $firstId = insertLegacyMoneyOrderRow(['tracking_number' => 'DUP-TRACK', 'total_amount' => 19.99]);
    insertLegacyMoneyOrderRow(['tracking_number' => 'DUP-TRACK', 'total_amount' => 29.99]);

    expect(fn () => runPaymentColumnsMigration())
        ->toThrow(RuntimeException::class, 'DUP-TRACK');

    // Abort-first: no index was created and no value backfill ran.
    expect(Schema::hasIndex($table, ['tracking_number'], 'unique'))->toBeFalse()
        ->and(DB::table($table)->find($firstId)->total_amount_cents)->toBeNull();
});

it('allows repeated NULL tracking numbers under the unique index', function () {
    insertLegacyMoneyOrderRow(['tracking_number' => null, 'total_amount' => 19.99]);
    insertLegacyMoneyOrderRow(['tracking_number' => null, 'total_amount' => 29.99]);

    runPaymentColumnsMigration();

    $rows = DB::table(moneyOrdersTable())->whereNull('tracking_number')->get();

    expect($rows)->toHaveCount(2)
        ->and((int) $rows[0]->total_amount_cents)->toBe(1999)
        ->and((int) $rows[1]->total_amount_cents)->toBe(2999);
});

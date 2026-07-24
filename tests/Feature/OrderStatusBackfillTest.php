<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rahat1994\SparkCommerce\Models\SCOrder;

function backfillOrdersTable(): string
{
    return (new SCOrder)->getTable();
}

/**
 * Insert a legacy-shaped order row straight through the query builder so the
 * enum casts on the model cannot rewrite the raw legacy values.
 *
 * @param  array<string, mixed>  $attributes
 */
function insertLegacyOrderRow(array $attributes = []): int
{
    return DB::table(backfillOrdersTable())->insertGetId(array_merge([
        'items' => json_encode([]),
        'total_amount' => 100,
        'tracking_number' => (string) Str::ulid(),
        'order_number' => 'ORD-' . fake()->unique()->numerify('######'),
        'status' => 'pending',
        'payment_status' => null,
        'shipping_status' => null,
        'meta' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function runOrderStatusBackfill(): void
{
    (include __DIR__ . '/../../database/migrations/backfill_order_statuses.php.stub')->up();
}

it('maps legacy statuses case-insensitively and preserves the original triple', function () {
    $pendingId = insertLegacyOrderRow([
        'status' => 'pending',
        'payment_status' => 'pending',
        'shipping_status' => 'pending',
    ]);
    $processingId = insertLegacyOrderRow([
        'status' => 'Processing',
        'payment_status' => 'paid',
        'shipping_status' => 'Processing',
    ]);
    $shippedId = insertLegacyOrderRow([
        'status' => 'Shipped',
        'payment_status' => 'Paid',
        'shipping_status' => 'Shipped',
    ]);
    $cancelledId = insertLegacyOrderRow([
        'status' => 'Cancelled',
        'payment_status' => null,
        'shipping_status' => 'Cancelled',
    ]);
    $canceledId = insertLegacyOrderRow([
        'status' => 'canceled',
        'payment_status' => null,
        'shipping_status' => null,
    ]);

    $rowCountBefore = DB::table(backfillOrdersTable())->count();

    runOrderStatusBackfill();

    expect(DB::table(backfillOrdersTable())->count())->toBe($rowCountBefore);

    $rows = DB::table(backfillOrdersTable())->get()->keyBy('id');

    expect($rows[$pendingId]->status)->toBe('awaiting_payment')
        ->and($rows[$pendingId]->payment_status)->toBe('pending')
        ->and($rows[$processingId]->status)->toBe('processing')
        ->and($rows[$processingId]->payment_status)->toBe('paid')
        ->and($rows[$shippedId]->status)->toBe('shipped')
        ->and($rows[$shippedId]->payment_status)->toBe('paid')
        ->and($rows[$cancelledId]->status)->toBe('cancelled')
        ->and($rows[$cancelledId]->payment_status)->toBeNull()
        ->and($rows[$canceledId]->status)->toBe('cancelled');

    $processingMeta = json_decode($rows[$processingId]->meta, true);

    expect($processingMeta['legacy_status'])->toBe([
        'status' => 'Processing',
        'payment_status' => 'paid',
        'shipping_status' => 'Processing',
    ]);

    $pendingMeta = json_decode($rows[$pendingId]->meta, true);

    expect($pendingMeta['legacy_status'])->toBe([
        'status' => 'pending',
        'payment_status' => 'pending',
        'shipping_status' => 'pending',
    ]);
});

it('aborts on an unmapped status value without changing anything', function () {
    $pendingId = insertLegacyOrderRow(['status' => 'pending']);
    insertLegacyOrderRow(['status' => 'on-hold']);

    expect(fn () => runOrderStatusBackfill())
        ->toThrow(RuntimeException::class, 'on-hold');

    $pendingRow = DB::table(backfillOrdersTable())->find($pendingId);

    expect($pendingRow->status)->toBe('pending')
        ->and(json_decode($pendingRow->meta, true))->toBe([]);
});

it('aborts on a NULL status listing the offending row ids', function () {
    $pendingId = insertLegacyOrderRow(['status' => 'pending']);
    $nullId = insertLegacyOrderRow(['status' => null]);

    expect(fn () => runOrderStatusBackfill())
        ->toThrow(RuntimeException::class, (string) $nullId);

    expect(DB::table(backfillOrdersTable())->find($pendingId)->status)->toBe('pending');
});

it('aborts on an unmapped payment_status value without changing anything', function () {
    $pendingId = insertLegacyOrderRow(['status' => 'pending', 'payment_status' => 'partial']);

    expect(fn () => runOrderStatusBackfill())
        ->toThrow(RuntimeException::class, 'partial');

    $row = DB::table(backfillOrdersTable())->find($pendingId);

    expect($row->status)->toBe('pending')
        ->and($row->payment_status)->toBe('partial');
});

it('is a no-op when run a second time', function () {
    insertLegacyOrderRow([
        'status' => 'pending',
        'payment_status' => 'pending',
        'shipping_status' => 'pending',
    ]);
    insertLegacyOrderRow([
        'status' => 'Shipped',
        'payment_status' => 'paid',
        'shipping_status' => 'Shipped',
    ]);

    runOrderStatusBackfill();

    $snapshot = DB::table(backfillOrdersTable())->orderBy('id')->get()
        ->map(fn (object $row): array => [
            'id' => $row->id,
            'status' => $row->status,
            'payment_status' => $row->payment_status,
            'shipping_status' => $row->shipping_status,
            'meta' => $row->meta,
        ])
        ->all();

    runOrderStatusBackfill();

    $afterSecondRun = DB::table(backfillOrdersTable())->orderBy('id')->get()
        ->map(fn (object $row): array => [
            'id' => $row->id,
            'status' => $row->status,
            'payment_status' => $row->payment_status,
            'shipping_status' => $row->shipping_status,
            'meta' => $row->meta,
        ])
        ->all();

    expect($afterSecondRun)->toBe($snapshot);
});

it('never overwrites an existing meta legacy_status entry', function () {
    $id = insertLegacyOrderRow([
        'status' => 'pending',
        'meta' => json_encode(['legacy_status' => ['status' => 'imported']]),
    ]);

    runOrderStatusBackfill();

    $row = DB::table(backfillOrdersTable())->find($id);

    expect($row->status)->toBe('awaiting_payment')
        ->and(json_decode($row->meta, true)['legacy_status'])->toBe(['status' => 'imported']);
});

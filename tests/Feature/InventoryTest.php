<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Rahat1994\SparkCommerce\Enums\BackorderPolicy;
use Rahat1994\SparkCommerce\Enums\OrderStatus;
use Rahat1994\SparkCommerce\Exceptions\IllegalOrderTransition;
use Rahat1994\SparkCommerce\Models\SCOrder;
use Rahat1994\SparkCommerce\Models\SCProduct;
use Rahat1994\SparkCommerce\Services\OrderTransitionService;

function inventoryProductsTable(): string
{
    return strval(config('sparkcommerce.table_prefix')) . strval(config('sparkcommerce.products_table_name'));
}

/**
 * Rebuild the products table in its LEGACY shape (decimal stock_quantity)
 * so the conversion stub has something real to convert. The provider's
 * migration list already ran the conversion during test setup, so the
 * live table is post-conversion by the time a test starts.
 */
function inventoryRebuildLegacyProductsTable(): void
{
    $prefix = strval(config('sparkcommerce.table_prefix'));

    Schema::dropIfExists($prefix . strval(config('sparkcommerce.product_variants_table_name')));
    Schema::dropIfExists($prefix . strval(config('sparkcommerce.products_table_name')));

    (include __DIR__ . '/../../database/migrations/create_sc_products_table.php.stub')->up();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function insertLegacyProductRow(array $attributes = []): int
{
    return DB::table(inventoryProductsTable())->insertGetId(array_merge([
        'name' => fake()->unique()->words(3, true),
        'slug' => fake()->unique()->slug(),
        'product_type' => 'simple',
        'regular_price' => 1000,
        'stock_quantity' => 10,
        'manage_product_stock' => 1,
        'should_allow_backorders' => 'do_not_allow',
        'created_at' => now(),
        'updated_at' => now(),
    ], $attributes));
}

function runStockQuantityConversion(): void
{
    (include __DIR__ . '/../../database/migrations/convert_stock_quantity_to_integer.php.stub')->up();
}

/**
 * An awaiting_payment order whose items snapshot references the product,
 * shaped exactly like the checkout snapshot (legacy keys + quantity).
 */
function inventoryAwaitingPaymentOrder(SCProduct $product, int $quantity): SCOrder
{
    return SCOrder::factory()->awaitingPayment()->create([
        'items' => [
            [
                'itemable_type' => SCProduct::class,
                'itemable_id' => $product->id,
                'quantity' => $quantity,
            ],
        ],
    ]);
}

it('maps the three legacy backorder strings onto the enum', function () {
    expect(BackorderPolicy::DoNotAllow->value)->toBe('do_not_allow')
        ->and(BackorderPolicy::Allow->value)->toBe('allow')
        ->and(BackorderPolicy::AllowNotifyCustomer->value)->toBe('allow_notify_customer')
        ->and(BackorderPolicy::DoNotAllow->allowsBackorder())->toBeFalse()
        ->and(BackorderPolicy::Allow->allowsBackorder())->toBeTrue()
        ->and(BackorderPolicy::AllowNotifyCustomer->allowsBackorder())->toBeTrue();
});

it('casts should_allow_backorders to a BackorderPolicy', function () {
    $product = SCProduct::factory()->create(['should_allow_backorders' => 'allow']);

    expect($product->fresh()->should_allow_backorders)->toBe(BackorderPolicy::Allow);
});

it('reports availability from managed stock and the backorder policy', function () {
    $managed = SCProduct::factory()->managedStock(3)->create();

    expect($managed->isAvailable(3))->toBeTrue()
        ->and($managed->isAvailable(4))->toBeFalse();

    $backorderable = SCProduct::factory()->managedStock(3)->create([
        'should_allow_backorders' => 'allow',
    ]);

    expect($backorderable->isAvailable(100))->toBeTrue();

    $unmanaged = SCProduct::factory()->create([
        'manage_product_stock' => false,
        'stock_quantity' => 0,
    ]);

    expect($unmanaged->isAvailable(100))->toBeTrue();
});

it('reserves stock atomically so only one reservation wins the last unit', function () {
    // Honest sqlite equivalent of two parallel checkouts: the guarded
    // decrement (WHERE stock_quantity >= qty) makes the race safe because
    // only one UPDATE can match the row. A driver-level parallel test
    // belongs to the host integration suite (U15).
    $product = SCProduct::factory()->managedStock(1)->create();

    $first = $product->reserveStock(1);
    $second = $product->reserveStock(1);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and((int) $product->fresh()->stock_quantity)->toBe(0);
});

it('decrements unguarded when the policy allows backorders', function () {
    $product = SCProduct::factory()->managedStock(1)->create([
        'should_allow_backorders' => 'allow',
    ]);

    expect($product->reserveStock(1))->toBeTrue()
        ->and($product->reserveStock(1))->toBeTrue();
});

it('never decrements or releases when stock is not managed', function () {
    $product = SCProduct::factory()->create([
        'manage_product_stock' => false,
        'stock_quantity' => 5,
    ]);

    expect($product->reserveStock(3))->toBeTrue();
    $product->releaseStock(3);

    expect((int) $product->fresh()->stock_quantity)->toBe(5);
});

it('restores stock when an awaiting_payment order is cancelled', function () {
    $product = SCProduct::factory()->managedStock(3)->create();
    $order = inventoryAwaitingPaymentOrder($product, 2);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Cancelled);

    expect((int) $product->fresh()->stock_quantity)->toBe(5);
});

it('restores stock when an awaiting_payment order expires', function () {
    $product = SCProduct::factory()->managedStock(3)->create();
    $order = inventoryAwaitingPaymentOrder($product, 2);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Expired);

    expect((int) $product->fresh()->stock_quantity)->toBe(5);
});

it('cannot double-release: an already-cancelled order refuses another transition', function () {
    // Release-once-only is guaranteed by the state machine itself:
    // Cancelled and Expired are terminal, so the transition that triggers
    // the release can only ever happen once per order.
    $product = SCProduct::factory()->managedStock(3)->create();
    $order = inventoryAwaitingPaymentOrder($product, 2);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Cancelled);

    expect(fn () => app(OrderTransitionService::class)->transition($order->fresh(), OrderStatus::Cancelled))
        ->toThrow(IllegalOrderTransition::class);

    expect((int) $product->fresh()->stock_quantity)->toBe(5);
});

it('does not restore stock on cancellation when stock is not managed', function () {
    $product = SCProduct::factory()->create([
        'manage_product_stock' => false,
        'stock_quantity' => 5,
    ]);
    $order = inventoryAwaitingPaymentOrder($product, 2);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Cancelled);

    expect((int) $product->fresh()->stock_quantity)->toBe(5);
});

it('does not restore stock when a paid order is cancelled', function () {
    // Paid-order refund restock is U13's job; the release listener only
    // reacts to AwaitingPayment -> Cancelled|Expired.
    $product = SCProduct::factory()->managedStock(3)->create();
    $order = SCOrder::factory()->paid()->create([
        'items' => [
            [
                'itemable_type' => SCProduct::class,
                'itemable_id' => $product->id,
                'quantity' => 2,
            ],
        ],
    ]);

    app(OrderTransitionService::class)->transition($order, OrderStatus::Cancelled);

    expect((int) $product->fresh()->stock_quantity)->toBe(3);
});

it('aborts the stock conversion listing rows with fractional stock', function () {
    inventoryRebuildLegacyProductsTable();

    insertLegacyProductRow(['stock_quantity' => 3]);
    $fractionalId = insertLegacyProductRow(['stock_quantity' => 2.5]);

    expect(fn () => runStockQuantityConversion())
        ->toThrow(RuntimeException::class, (string) $fractionalId);

    // Nothing was changed: the column keeps its legacy type and values.
    expect(strtolower(Schema::getColumnType(inventoryProductsTable(), 'stock_quantity')))
        ->not->toContain('int');
    expect((float) DB::table(inventoryProductsTable())->find($fractionalId)->stock_quantity)
        ->toBe(2.5);
});

it('converts clean stock rows to an integer column preserving values', function () {
    inventoryRebuildLegacyProductsTable();

    $wholeId = insertLegacyProductRow(['stock_quantity' => 3.00]);
    $nullId = insertLegacyProductRow(['stock_quantity' => null]);

    runStockQuantityConversion();

    expect(strtolower(Schema::getColumnType(inventoryProductsTable(), 'stock_quantity')))
        ->toContain('int');

    $rows = DB::table(inventoryProductsTable())->get()->keyBy('id');

    expect((int) $rows[$wholeId]->stock_quantity)->toBe(3)
        ->and($rows[$nullId]->stock_quantity)->toBeNull();
});

it('is a no-op when the stock conversion runs twice', function () {
    inventoryRebuildLegacyProductsTable();

    $id = insertLegacyProductRow(['stock_quantity' => 7]);

    runStockQuantityConversion();
    runStockQuantityConversion();

    expect(strtolower(Schema::getColumnType(inventoryProductsTable(), 'stock_quantity')))
        ->toContain('int');
    expect((int) DB::table(inventoryProductsTable())->find($id)->stock_quantity)->toBe(7);
});

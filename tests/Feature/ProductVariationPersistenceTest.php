<?php

use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\CreateProduct;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource\Pages\EditProduct;
use Rahat1994\SparkCommerce\Models\SCProduct;
use Rahat1994\SparkCommerce\Models\SCProductVariation;

it('uses the product variations table', function () {
    expect((new SCProductVariation)->getTable())->toBe('sc_product_variations');
});

it('adds the missing variation columns idempotently via the corrective migration', function () {
    $stub = __DIR__ . '/../../database/migrations/complete_sc_product_variations_table.php.stub';

    expect(file_exists($stub))->toBeTrue();

    // Running the corrective migration twice must be a no-op.
    (include $stub)->up();
    (include $stub)->up();

    expect(Schema::hasColumn('sc_product_variations', 'stock_quantity'))->toBeTrue()
        ->and(Schema::hasColumn('sc_product_variations', 'attribute_combination'))->toBeTrue();
});

it('persists variations to the variations table when creating a variable product', function () {
    $this->actingAs($this->createAdminUser());

    Livewire::test(CreateProduct::class)
        ->fillForm([
            'name' => 'Variable Shirt',
            'product_type' => 'variable',
            'product_attributes' => [
                [
                    'attribute_name' => 'Size',
                    'attribute_values' => ['Small', 'Large'],
                    'visible_on_the_product_page' => ['visible_on_the_product_page', 'used_for_variations'],
                ],
            ],
            'generate_varaitions' => 'generate_variations_from_attributes',
            'product_variations' => [
                [
                    'title' => 'Size: Small',
                    'sku' => 'SHIRT-S',
                    'variation_options' => ['enabled'],
                    'regular_price' => 10,
                    'sale_price' => 8,
                ],
                [
                    'title' => 'Size: Large',
                    'sku' => 'SHIRT-L',
                    'variation_options' => ['enabled', 'virtual'],
                    'regular_price' => 12,
                    'sale_price' => 9,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = SCProduct::where('name', 'Variable Shirt')->firstOrFail();

    expect($product->variations()->count())->toBe(2);

    $this->assertDatabaseHas('sc_product_variations', [
        'product_id' => $product->id,
        'sku' => 'SHIRT-S',
        'variation_title' => 'Size: Small',
        'enabled' => 1,
        'virtual' => 0,
    ]);

    $this->assertDatabaseHas('sc_product_variations', [
        'product_id' => $product->id,
        'sku' => 'SHIRT-L',
        'variation_title' => 'Size: Large',
        'enabled' => 1,
        'virtual' => 1,
    ]);
});

it('reloads variations into the edit form and keeps them on save', function () {
    $this->actingAs($this->createAdminUser());

    $product = SCProduct::factory()->variable()->create();

    $product->variations()->create([
        'sku' => 'VAR-A',
        'variation_title' => 'Color: Red',
        'enabled' => true,
        'regular_price' => 10,
        'sale_price' => 8,
    ]);

    $product->variations()->create([
        'sku' => 'VAR-B',
        'variation_title' => 'Color: Blue',
        'enabled' => true,
        'regular_price' => 12,
        'sale_price' => 9,
    ]);

    Livewire::test(EditProduct::class, ['record' => $product->slug])
        ->assertFormSet(function (array $state) {
            $variations = array_values($state['product_variations'] ?? []);

            expect($variations)->toHaveCount(2)
                ->and(array_column($variations, 'sku'))->toContain('VAR-A', 'VAR-B')
                ->and(array_column($variations, 'title'))->toContain('Color: Red', 'Color: Blue');
        })
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->variations()->count())->toBe(2)
        ->and($product->variations()->pluck('sku')->all())->toContain('VAR-A', 'VAR-B');
});

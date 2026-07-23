<?php

use Filament\Facades\Filament;
use Rahat1994\SparkCommerce\Filament\Resources\ProductResource;
use Rahat1994\SparkCommerce\Models\SCCategory;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;

it('returns all categories when no multivendor package is installed', function () {
    SCCategory::factory()->count(3)->create();

    $categories = ProductResource::getShopCategories();

    expect($categories)->toHaveCount(3);
});

it('renders the product create page with the category picker standalone', function () {
    $this->actingAs(User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]));

    SCCategory::factory()->count(2)->create();

    $this->get(route('filament.admin.resources.products.create'))->assertSuccessful();
});

it('ignores a stray multivendor class alias when the vendor model class is absent', function () {
    // A leftover "SparkcommerceMultivendor" facade alias must not trigger
    // vendor scoping when the actual multivendor package is not installed.
    if (! class_exists('SparkcommerceMultivendor', false)) {
        class_alias(stdClass::class, 'SparkcommerceMultivendor');
    }

    $user = User::create([
        'name' => 'Tenant',
        'email' => 'tenant@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    Filament::setTenant($user);

    SCCategory::factory()->count(2)->create();

    expect(ProductResource::getShopCategories())->toHaveCount(2);
});

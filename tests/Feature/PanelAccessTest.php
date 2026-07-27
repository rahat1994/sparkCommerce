<?php

use Rahat1994\SparkCommerce\Tests\Fixtures\AllowEveryonePanelGate;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;

dataset('resource index routes', [
    'products' => 'filament.admin.resources.products.index',
    'categories' => 'filament.admin.resources.categories.index',
    'orders' => 'filament.admin.resources.orders.index',
    'coupons' => 'filament.admin.resources.coupons.index',
    'reviews' => 'filament.admin.resources.reviews.index',
    'users' => 'filament.admin.resources.users.index',
]);

it('forbids every resource index for a user without the admin role', function (string $routeName) {
    $this->actingAs(User::create([
        'name' => 'Customer',
        'email' => 'customer@example.com',
        'password' => bcrypt('password'),
    ]));

    $this->get(route($routeName))->assertForbidden();
})->with('resource index routes');

it('serves every resource index once the published admin role is assigned', function (string $routeName) {
    $this->artisan('sc:publish-roles')->assertSuccessful();

    $user = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);

    $user->assignRole(config('sparkcommerce.admin_role'));

    $this->actingAs($user);

    $this->get(route($routeName))->assertSuccessful();
})->with('resource index routes');

it('lets a panel_gate override grant access to a role-less user', function () {
    config()->set('sparkcommerce.panel_gate', AllowEveryonePanelGate::class);

    $this->actingAs(User::create([
        'name' => 'Customer',
        'email' => 'customer@example.com',
        'password' => bcrypt('password'),
    ]));

    $this->get(route('filament.admin.resources.products.index'))->assertSuccessful();
});

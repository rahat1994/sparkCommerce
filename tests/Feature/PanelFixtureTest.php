<?php

use Rahat1994\SparkCommerce\Tests\Fixtures\User;

it('serves the panel root to a user with the admin role', function () {
    $this->actingAs($this->createAdminUser());

    // The fixture panel registers no dashboard page, so Filament redirects
    // the panel root to the first resource.
    $this->get('/admin')->assertRedirect(route('filament.admin.resources.products.index'));
});

it('forbids the products resource index for a user without the admin role', function () {
    $this->actingAs(User::create([
        'name' => 'Customer',
        'email' => 'customer@example.com',
        'password' => bcrypt('password'),
    ]));

    $this->get(route('filament.admin.resources.products.index'))->assertForbidden();
});

it('renders the products resource index for a user with the admin role', function () {
    $this->actingAs($this->createAdminUser());

    $this->get(route('filament.admin.resources.products.index'))->assertSuccessful();
});

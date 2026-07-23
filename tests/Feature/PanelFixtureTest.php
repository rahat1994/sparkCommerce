<?php

use Rahat1994\SparkCommerce\Tests\Fixtures\User;

it('serves the panel root to an authenticated user', function () {
    $this->actingAs(User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]));

    // The fixture panel registers no dashboard page, so Filament redirects
    // the panel root to the first resource.
    $this->get('/admin')->assertRedirect(route('filament.admin.resources.products.index'));
});

it('renders the products resource index on the fixture panel', function () {
    $this->actingAs(User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]));

    $this->get(route('filament.admin.resources.products.index'))->assertSuccessful();
});

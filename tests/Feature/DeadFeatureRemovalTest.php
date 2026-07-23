<?php

use Rahat1994\SparkCommerce\Tests\Fixtures\User;

it('renders the product create page without coming soon placeholder tabs', function () {
    $this->actingAs(User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]));

    $this->get(route('filament.admin.resources.products.create'))
        ->assertSuccessful()
        ->assertDontSee('Coming Soon');
});

it('does not ship the empty scaffolding class and facade', function () {
    expect(class_exists('Rahat1994\SparkCommerce\SparkCommerce'))->toBeFalse()
        ->and(class_exists('Rahat1994\SparkCommerce\Facades\SparkCommerce'))->toBeFalse();
});

<?php

use Rahat1994\SparkCommerce\Models\SCOrder;

it('throws a clear exception when vendor is accessed without a configured vendor model', function () {
    expect(fn () => (new SCOrder)->vendor())
        ->toThrow(RuntimeException::class, 'sparkcommerce.vendor_model is not configured');
});

it('renders the user create page without vendor fields standalone', function () {
    $this->actingAs($this->createAdminUser());

    $this->get(route('filament.admin.resources.users.create'))
        ->assertSuccessful()
        ->assertDontSee('Vendors');
});

<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Forms\Components\TextInput;

trait HasDimension
{
    public static function getDimensionInputes(): array
    {
        return [
            TextInput::make('weight')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.weight'))->columnSpan(3),
            TextInput::make('height')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.height')),
            TextInput::make('width')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.width')),
            TextInput::make('length')->numeric()->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.product_dimension.length')),
        ];
    }
}

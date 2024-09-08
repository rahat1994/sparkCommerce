<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Radio;


trait HasPrice
{

    /**
     * @return array
     */
    protected static function getPriceInputs(): array
    {
        return [
            TextInput::make('regular_price')
                ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.regular_price'))
                ->numeric(),
            TextInput::make('sale_price')
                ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.sale_price'))
                ->numeric(),
        ];
    }
}

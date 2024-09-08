<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Radio;


trait HasInventory
{

    /**
     * @return array
     */
    protected static function getInventoryInputs(): array
    {
        return [
            TextInput::make('sku')
                ->label(__('sparkcommerce::sparkcommerce.resource.product.creation_form.sku')),
            Placeholder::make('notice')
                ->content(new HtmlString('<p>Needs work like woocommerce</p>')),
            Section::make('Inventory')
                ->description('Settings for inventory')
                ->schema([
                    TextInput::make('stock_quantity')
                        ->label('Stock Quantity')->numeric(),
                    Radio::make('should_allow_backorders')
                        ->label('Allow backorders?')
                        ->options([
                            'do_not_allow' => 'Do not allow',
                            'allow_notify_customer' => 'Allow, but notify customer',
                            'allow' => 'Allow',
                        ])->default('do_not_allow')->inline()->inlineLabel(false),
                    TextInput::make('low_stock_threshold')
                        ->label('Low stock threshold')->numeric(),
                ]),
        ];
    }
}

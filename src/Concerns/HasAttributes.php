<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;

trait HasAttributes
{
    public static function getAttributesTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.attributes'))
            ->schema([
                Repeater::make('product_attributes')
                    ->label('Product Attributes')
                    ->schema([
                        Fieldset::make('product_attributes')
                            ->label('Product Attributes')
                            ->schema([
                                CheckboxList::make('visible_on_the_product_page')
                                    ->options([
                                        'visible_on_the_product_page' => 'Visible on the product page',
                                        'used_for_variations' => 'Used for varaitions',
                                    ])
                                    ->default(['visible_on_the_product_page', 'used_for_variations'])
                                    ->label('Visible on the product page')->columnSpan(2),
                                TextInput::make('attribute_name')
                                    ->label('Attribute Name'),
                                Repeater::make('attribute_values')
                                    ->label('Attribute Values')
                                    ->simple(
                                        TextInput::make('attribute_value')
                                            ->label('Attribute Value'),
                                    )->collapsible(),

                            ]),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(
                        fn(array $state): ?string => $state['attribute_name'] ?? null
                    ),
            ]);
    }
}

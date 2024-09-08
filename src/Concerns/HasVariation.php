<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Forms\Set;

trait HasVariation
{

    public static function getVariationsRepeaterField()
    {

        return [
            Section::make('Variations')
                ->schema(function (Get $get) {
                    $generate_variations = $get('generate_varaitions');
                    // dd($generate_variations);
                    if ($generate_variations == null) {
                        return [];
                    } elseif ($generate_variations == 'create_variations_manually') {
                        return [Placeholder::make('Info')
                            ->content(new HtmlString('<p>Generate Manually</p>'))];
                    } else {
                        return [
                            Repeater::make('product_variations')
                                ->label('Product variations')
                                ->collapsible()
                                ->collapsed()
                                ->schema([
                                    Fieldset::make('variation')
                                        ->label('Variation')
                                        ->schema([
                                            Group::make([
                                                FileUpload::make('variation_image')
                                                    ->image(),
                                                TextInput::make('sku')
                                                    ->label('SKU'),
                                            ])->columnSpanFull(),
                                            CheckboxList::make('variation_options')
                                                ->options([
                                                    'enabled' => 'Enabled',
                                                    'downloadable' => 'Downloadable',
                                                    'virtual' => 'Virtual',
                                                    // 'manage_stock' => 'Manage Stock',
                                                ])
                                                ->default('enabled')
                                                ->label('Visible on the product page')->columnSpan(2),

                                            TextInput::make('regular_price'),
                                            TextInput::make('sale_price'),
                                            Select::make('stock_status')
                                                ->options([
                                                    'instock' => 'In Stock',
                                                    'outofstock' => 'Out of Stock',
                                                ])->default('instock'),

                                            self::getProductDimensionFields(),
                                            RichEditor::make('description')
                                                ->label('Description')->columnSpan(2),

                                        ]),
                                ])->itemLabel(
                                    fn(array $state): ?string => $state['title'] ?? null
                                ),
                        ];
                    }
                })->hidden(fn(Get $get) => $get('generate_varaitions') == null),
        ];
    }

    public static function getVariationsTab(): Tab
    {
        return Tab::make(__('sparkcommerce::sparkcommerce.resource.product.creation_form.tabs_section.tabs.variations'))
            ->schema(function (Get $get) {

                $attributes = array_values($get('product_attributes'));

                if (count($attributes) > 0 && $attributes[0]['attribute_name'] == null) {
                    return [Placeholder::make('Info')
                        ->content(new HtmlString('<p>Please add attributes</p>'))];
                } else {
                    return array_merge([Fieldset::make('Create Variations')->schema(
                        [Select::make('generate_varaitions')
                            ->label('Create Variations')
                            ->options([
                                'generate_variations_from_attributes' => 'Generate Variations from Attributes',
                                'create_variations_manually' => 'Create Variations manually',
                            ]), Actions::make([
                            FormAction::make('Select')
                                ->icon('heroicon-m-bars-3')
                                ->action(function (Get $get, Set $set, $state) {
                                    $set('product_variations', self::generateVariations($get('product_attributes')));
                                }),
                        ])->verticalAlignment(VerticalAlignment::End)]
                    )], self::getVariationsRepeaterField());
                }
            })->hidden(fn(Get $get) => $get('product_type') == 'simple');
    }

    public static function generateVariations($data)
    {

        $variations = array_map(function ($attribute) {
            return array_map(function ($value) use ($attribute) {
                return $attribute['attribute_name'] . ': ' . $value['attribute_value'];
            }, $attribute['attribute_values']);
        }, $data);

        $repeater_value = array_map(function ($attributes) {
            $values = implode(' ', $attributes);

            return [
                'title' => $values,
                'sku' => $values,
            ];
        }, self::variationCombinations(array_values($variations)));

        return $repeater_value;
    }

    public static function variationCombinations($arrays, $i = 0)
    {

        if (! isset($arrays[$i])) {
            return [];
        }
        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }

        // get combinations from subsequent arrays
        $tmp = self::variationCombinations(array_values($arrays), $i + 1);

        $result = [];

        // concat each array from tmp with each element from $arrays[$i]
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ?
                    array_merge([$v], $t) :
                    [$v, $t];
            }
        }

        return $result;
    }
}

<?php

namespace Rahat1994\SparkCommerce\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Support\Arrayable;

class CategoriesField extends Field
{
    // Referencing views from my own package.
    protected string $view = 'sparkcommerce::forms.components.categories-field';

    /**
     * @var array<string | array<string>> | Arrayable | string | Closure | null
     */
    protected array | Arrayable | string | Closure | null $categories = null;

    /**
     * @param  array<string | array<string>> | Arrayable | string | Closure | null  $options
     */
    public function categories(array | Arrayable | string | Closure | null $categories): static
    {
        // dd($categories);
        $this->categories = $categories;
        return $this;
    }

    /**
     * @return array<string | array<string>>
     */
    public function getOptions(): array
    {
        $options = $this->evaluate($this->categories) ?? [];

        return $options;
    }

    public function getCategories(): array
    {
        return [
            [
                'label' => 'All Categories',
                'value' => 'category-1',
                'children' => [
                    [
                        'label' => 'Subcategory 1',
                        'value' => 'subcategory-1',
                    ],
                    [
                        'label' => 'Subcategory 2',
                        'value' => 'subcategory-2',
                        'children' => [
                            [
                                'label' => 'Subsubcategory 1',
                                'value' => 'subsubcategory-1',
                            ],
                            [
                                'label' => 'Subsubcategory 2',
                                'value' => 'subsubcategory-2',
                            ],
                        ]
                    ],
                ]
            ],

            [
                'label' => 'All Categories 2',
                'value' => 'category-1',
                'children' => [
                    [
                        'label' => 'Subcategory 1',
                        'value' => 'subcategory-1',
                    ],
                    [
                        'label' => 'Subcategory 2',
                        'value' => 'subcategory-2',
                        'children' => [
                            [
                                'label' => 'Subsubcategory 1',
                                'value' => 'subsubcategory-1',
                            ],
                            [
                                'label' => 'Subsubcategory 2',
                                'value' => 'subsubcategory-2',
                            ],
                        ]
                    ],
                ]
            ]
        ];
    }
}

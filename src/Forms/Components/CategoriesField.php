<?php

namespace Rahat1994\SparkCommerce\Forms\Components;

use Closure;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Contracts\Support\Arrayable;

class CategoriesField extends CheckboxList
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
        $this->categories = $categories;

        return $this;
    }

    /**
     * @return array<T> | array<T>
     */
    public function getOptions(): array
    {
        $options = $this->evaluate($this->categories) ?? [];

        return $options;
    }

    public function getParent($index): string
    {
        if ($index === null) {
            return '';
        }

        return ' (' . $this->getOptions()[$index]['name'] . ')';
    }
}

@php
    $gridDirection = 'column';
    $isBulkToggleable = false;
    $isDisabled = false;
    $isSearchable = false;
    $statePath = $getStatePath();
    $categories = $getOptions();
@endphp


<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data='{
            categories: <?=json_encode($categories)?>,
            state: $wire.$entangle("{{ $getStatePath() }}")
        }'
        x-init="
            let categoriesContainer = document.getElementById('category_checkboxes');

            categories.forEach(category => {
                let checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.id = category.id;
                checkbox.value = category.id;

                checkbox.setAttribute('x-model', 'state');

                let label = document.createElement('label');
                label.htmlFor = category.id;
                label.appendChild(document.createTextNode(category.name));

                categoriesContainer.appendChild(checkbox);
                categoriesContainer.appendChild(label);
            });
        "
    >
        <x-filament::grid
            :default="$getColumns('default')"
            :sm="$getColumns('sm')"
            :md="$getColumns('md')"
            :lg="$getColumns('lg')"
            :xl="$getColumns('xl')"
            :two-xl="$getColumns('2xl')"
            :direction="$gridDirection"
            :x-show="$isSearchable ? 'visibleCheckboxListOptions.length' : null"
            :attributes="
                \Filament\Support\prepare_inherited_attributes($attributes)
                    ->merge($getExtraAttributes(), escape: false)
                    ->class([
                        'fi-fo-checkbox-list gap-4',
                        '-mt-4' => $gridDirection === 'column',
                    ])
            "
        >
        <div id="category_checkboxes">

        </div>
        </x-filament::grid>
        <br />
        @include('sparkcommerce::forms.components.add-categories')
    </div>
</x-dynamic-component>

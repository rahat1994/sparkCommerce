@php
    $gridDirection = 'column';
    $isBulkToggleable = false;
    $isDisabled = false;
    $isSearchable = false;
    $statePath = $getStatePath();
    // dd($field);
    // return;
@endphp


<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            categorySelected(e){
                let value = e.target.getAttribute('value');
                state.push(value);
            }
        }"
        x-init="$nextTick(() => {

            const checkboxes = document.querySelectorAll('[data-category-id]');
            const checkboxMap = {};

            // Create a map of checkboxes by their ID
            checkboxes.forEach(checkbox => {
                const id = checkbox.getAttribute('data-category-id');
                const parentId = checkbox.getAttribute('data-category-parent-id');
                checkboxMap[id] = { element: checkbox, parentId: parentId, children: [] };
            });

            console.log(checkboxMap);
            // Build the parent-child relationships
            Object.values(checkboxMap).forEach(checkbox => {
                if (checkbox.parentId) {
                    checkboxMap[checkbox.parentId].children.push(checkbox);
                }
            });

            // Function to recursively append children and apply margin
            function appendChildren(parent, children, level) {
                children.forEach(child => {
                    child.element.style.marginLeft = `${level * 8}px`;
                    parent.appendChild(child.element);
                    appendChildren(child.element, child.children, level + 1);
                });
            }

            // Find root elements (those without a parent) and start the reorganization
            const rootElements = Object.values(checkboxMap).filter(checkbox => !checkbox.parentId);
            const container = document.getElementById('checkbox-container');

            rootElements.forEach(root => {
                container.appendChild(root.element);
                appendChildren(root.element, root.children, 1);
            });

        })"
        x-on:category-created.window="
            let value = event.detail.id;
            console.log(value);
            let selector = `input[value='${value}']`;
            console.log(selector);
            let input = document.querySelector(selector);
            console.log(input);
            if (input) {
                input.checked = true;
            }
        "
    >
        <div class="p-2 border border-gray-400 overflow-x-hidden" style="height: 10rem; overflow-y:scroll">
            <x-filament::grid
                :default="$getColumns('default')"
                :sm="$getColumns('sm')"
                :md="$getColumns('md')"
                :lg="$getColumns('lg')"
                :xl="$getColumns('xl')"
                :two-xl="$getColumns('2xl')"
                direction="column"
                :x-show="$isSearchable ? 'visibleCheckboxListOptions.length' : null"
                :attributes="
                    \Filament\Support\prepare_inherited_attributes($attributes)
                        ->merge($getExtraAttributes(), escape: false)
                        ->merge([
                            'id' => 'checkbox-container',
                        ])
                        ->class([
                            'fi-fo-checkbox-list gap-4',
                        ])
                "
            >
                @forelse ($getOptions() as $value => $label)
                    <div
                        wire:key="{{ $this->getId() }}.{{ $statePath }}.{{ $field::class }}.options.{{ $value }}"
                        @class([
                            'break-inside-avoid pt-2' => $gridDirection === 'column',
                        ])
                        @if($label['parent_id'] != null)
                            data-category-parent-id="{{ $label['parent_id'] }}"
                        @endif

                        @if($label['id'] != null)
                            data-category-id="{{ $label['id'] }}"
                        @endif
                    >
                        <label
                            class="fi-fo-checkbox-list-option-label flex gap-x-3"
                        >
                            <x-filament::input.checkbox
                                :valid="! $errors->has($statePath)"
                                :attributes="
                                    \Filament\Support\prepare_inherited_attributes($getExtraInputAttributeBag())
                                        ->merge([
                                            'class' => 'categories',
                                            'disabled' => $isDisabled,
                                            'value' => $label['id'],
                                            'wire:loading.attr' => 'disabled',
                                            $applyStateBindingModifiers('wire:model') => $statePath,
                                        ], escape: false)
                                        ->class(['mt-1'])
                                "
                            />

                            <div class="grid text-sm">
                                <span
                                    class="fi-fo-checkbox-list-option-label overflow-hidden break-words font-medium text-gray-950 dark:text-white"
                                >
                                    {{ $label['name'] }}
                                </span>
                            </div>
                        </label>
                    </div>
                @empty
                    <div
                        wire:key="{{ $this->getId() }}.{{ $statePath }}.{{ $field::class }}.empty"
                    ></div>
                @endforelse
            </x-filament::grid>
        </div>

        <br />
        @include('sparkcommerce::forms.components.add-categories')
    </div>
</x-dynamic-component>

@php
    $gridDirection = 'column';
    $isBulkToggleable = false;
    $isDisabled = false;
    $isSearchable = false;
    $statePath = $getStatePath();
    // dd($this->getId().$statePath);
    // return;
@endphp


<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data=""
        x-init=""
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
            @forelse ($getOptions() as $value => $label)
                <div
                    wire:key="{{ $this->getId() }}.{{ $statePath }}.{{ $field::class }}.options.{{ $value }}"
                    @class([
                        'break-inside-avoid pt-2' => $gridDirection === 'column',
                    ])
                >
                    <label
                        class="fi-fo-checkbox-list-option-label flex gap-x-3"
                    >
                        <x-filament::input.checkbox
                            :valid="! $errors->has($statePath)"
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
        <br />
        @include('sparkcommerce::forms.components.add-categories')
    </div>
</x-dynamic-component>

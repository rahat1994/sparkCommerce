@php
$options = $getOptions();
@endphp
    <div
        x-data="{
            showForm: false,
            category: null,
            categoryName: '',
            formProcessing:false,
            async createNewCategory() {
                this.formProcessing = true;
                await $wire.saveCategory(this.categoryName,this.category);
                this.formProcessing = false;

                $wire.$refresh();
            }
        }"
        x-init="console.log(showForm)"
    >
        <div x-show="showForm" x-transition>

            <span class="p-2">
                <div class="flex items-center justify-between gap-x-3 ">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3" for="data.product_type">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Product Type
                        </span>
                    </label>
                </div>
            <x-filament::input.wrapper
            :attributes="
            \Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())
                ->class(['fi-fo-select'])
            ">


                <x-filament::input.select
                x-model='category'
                :attributes="
                    $getExtraInputAttributeBag()
                        ->merge([
                            'class' => 'p-4',
                        ], escape: false)
                "
                >
                    @foreach($options as $value => $label)
                        <option value="{{$value}}">{{$label['name']}}</option>
                    @endforeach
                </x-filament::input.select>

            </x-filament::input.wrapper>
            </span>

            <span class="p-2">
                <div class="flex items-center justify-between gap-x-3 ">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-3">
                        <span class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                            Name
                        </span>
                    </label>
                </div>
                <x-filament::input.wrapper>

                    <x-filament::input
                        x-model="categoryName"
                    />
                </x-filament::input.wrapper>
            </span>
            <br/>
            <x-filament::button x-show="!formProcessing" x-on:click="createNewCategory" size="xs">
                Create
            </x-filament::button>

            <x-filament::loading-indicator x-show="formProcessing" class="h-5 w-5" />
        </div>
        
        <x-filament::link
            x-on:click="showForm = !showForm"
            x-show="!showForm"
            tag="button"
        >
            + Add New Category
        </x-filament::link>

        <!-- <a class="pt-4" x-on:click="" ></a> -->
    </div>

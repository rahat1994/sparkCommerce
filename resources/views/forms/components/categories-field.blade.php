<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{ state: $wire.$entangle('{{ $getStatePath() }}') }">
        <ul id="myUL">
            <li><input type="checkbox"><span >Beverages</span>
                <ul class="nested ps-4">
                <li><input type="checkbox">Water</li>
                <li><input type="checkbox">Coffee</li>
                <li><input type="checkbox"><span >Tea</span>
                    <ul class="ps-8">
                    <li><input type="checkbox">Black Tea</li>
                    <li><input type="checkbox">White Tea</li>
                    <li><input type="checkbox"><span >Green Tea</span>
                        <ul class="nested ps-12">
                        <li><input type="checkbox">Sencha</li>
                        <li><input type="checkbox">Gyokuro</li>
                        <li><input type="checkbox">Matcha</li>
                        <li><input type="checkbox">Pi Lo Chun</li>
                        </ul>
                    </li>
                    </ul>
                </li>  
                </ul>
            </li>
        </ul>
    </div>

<script>
    var toggler = document.getElementsByClassName("caret");
    var i;

    for (i = 0; i < toggler.length; i++) {
    toggler[i].addEventListener("click", function() {
        this.parentElement.querySelector(".nested").classList.toggle("active");
        this.classList.toggle("caret-down");
    });
    }
</script>
<style>
    ul, #myUL {
        list-style-type: none;
    }

    #myUL {
        margin: 0;
        padding: 0;
    }
</style>

</x-dynamic-component>
@props(['placeholder' => 'Search...', 'name' => 'search'])

<div class="relative w-full">
    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">search</span>
    <input
        type="text"
        name="{{ $name }}"
        placeholder="{{ $placeholder }}"
        {{ $attributes->merge(['class' => 'form-input pl-10 shadow-sm']) }}
    />
</div>

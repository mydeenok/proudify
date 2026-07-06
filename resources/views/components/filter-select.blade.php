@props(['name', 'label' => null])

<div class="relative">
    @if ($label)
        <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    @endif
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'appearance-none h-[44px] pl-sm pr-10 bg-surface border border-outline-variant rounded-lg font-body-sm text-body-sm text-on-surface focus:outline-none focus:border-primary-container focus:ring-1 focus:ring-primary-container cursor-pointer shadow-sm min-w-[140px]']) }}
    >
        {{ $slot }}
    </select>
    <span class="material-symbols-outlined absolute right-sm top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none text-[20px]">expand_more</span>
</div>

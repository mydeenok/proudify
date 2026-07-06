@props(['active' => false, 'icon'])

@php
$classes = ($active ?? false)
    ? 'flex items-center gap-sm px-sm py-xs bg-primary-container text-on-primary rounded-lg text-sm font-bold shadow-sm translate-x-0.5 transition-all duration-200'
    : 'flex items-center gap-sm px-sm py-xs text-on-surface-variant hover:bg-surface-container-high rounded-lg transition-all text-sm';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <span class="material-symbols-outlined text-lg" @if($active) style="font-variation-settings: 'FILL' 1;" @endif>{{ $icon }}</span>
    {{ $slot }}
</a>

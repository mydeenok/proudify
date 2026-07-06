@props(['active' => false])

@php
$classes = ($active ?? false) ? 'nav-tab-active' : 'nav-tab';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>

@props(['variant' => 'brand', 'href' => '/'])

@php
$variants = [
    'icon' => ['img' => 'h-8 w-auto max-w-[120px]', 'showTagline' => false],
    'brand' => ['img' => 'h-8 w-auto max-w-[140px]', 'showTagline' => false],
    'full' => ['img' => 'h-12 w-auto max-w-[200px]', 'showTagline' => false],
    'sidebar' => ['img' => 'h-9 w-auto max-w-[160px]', 'showTagline' => true],
];
$config = $variants[$variant] ?? $variants['brand'];
$logoUrl = asset('images/proudify-logo.png');
@endphp

<a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => 'inline-flex items-center gap-sm shrink-0' . ($config['showTagline'] ? ' flex-col items-start gap-xs' : '')]) }}>
    <img
        src="{{ $logoUrl }}"
        alt="{{ config('app.name') }}"
        class="{{ $config['img'] }} object-contain shrink-0"
        width="200"
        height="48"
    />
    @if ($config['showTagline'])
        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Admin Console</span>
    @endif
</a>

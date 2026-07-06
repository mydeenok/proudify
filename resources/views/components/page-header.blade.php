@props([
    'title',
    'description' => null,
])

<header {{ $attributes->merge(['class' => 'flex flex-col md:flex-row md:items-center justify-between gap-md mb-margin']) }}>
    <div>
        <h1 class="font-headline-xl text-headline-xl text-on-surface tracking-tight">{{ $title }}</h1>
        @if ($description)
            <p class="font-body-md text-body-md text-on-surface-variant mt-xs">{{ $description }}</p>
        @endif
    </div>
    @if (isset($actions))
        <div class="flex flex-wrap items-center gap-sm shrink-0">
            {{ $actions }}
        </div>
    @endif
</header>

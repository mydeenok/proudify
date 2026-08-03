@props(['title' => null, 'backUrl' => null, 'backLabel' => 'Back', 'subtitle' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-head :title="$title" />
</head>
<body class="h-full flex flex-col bg-floor font-body-md text-on-surface antialiased overflow-hidden">
    <header class="flex justify-between items-center px-margin py-md border-b border-outline-variant bg-surface sticky top-0 z-10 shadow-sm shrink-0">
        <div class="flex items-center gap-md">
            @if ($backUrl)
                <a href="{{ $backUrl }}" wire:navigate class="text-on-surface-variant hover:text-primary transition-colors flex items-center justify-center w-10 h-10 rounded-full hover:bg-surface-variant">
                    <span class="material-symbols-outlined">arrow_back</span>
                </a>
            @endif
            <div>
                @if ($title)
                    <h1 class="font-headline-md text-headline-md text-on-surface">{{ $title }}</h1>
                @endif
                @if ($subtitle)
                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @if (isset($actions))
            <div class="flex items-center gap-sm">
                {{ $actions }}
            </div>
        @endif
    </header>

    <div class="flex-1 overflow-auto">
        {{ $slot }}
    </div>
</body>
</html>

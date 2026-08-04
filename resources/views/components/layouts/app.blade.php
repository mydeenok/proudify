@props([
    'title' => null,
    'fullWidth' => false,
    'showStatus' => true,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-head :title="$title" />
</head>
<body class="bg-floor min-h-screen flex flex-col font-body-md text-on-background antialiased">
    <x-site-header />

    <main @class([
        'flex-grow w-full',
        'max-w-[1200px] mx-auto px-margin py-2xl' => ! $fullWidth,
    ])>
        @if ($showStatus && session('status'))
            <div class="mb-lg alert-success {{ $fullWidth ? 'max-w-[1200px] mx-auto px-margin pt-2xl' : '' }}">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <x-site-footer />
</body>
</html>

@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-head :title="$title" />
</head>
<body class="bg-floor min-h-screen flex flex-col antialiased selection:bg-primary selection:text-on-primary">
    <x-site-header />

    <main class="flex-grow flex items-center justify-center p-md relative">
        <div aria-hidden="true" class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute top-[-10%] left-[-5%] w-[40%] h-[40%] rounded-full bg-primary-fixed opacity-30 blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-5%] w-[40%] h-[40%] rounded-full bg-secondary-fixed opacity-40 blur-[100px]"></div>
        </div>

        <div class="w-full max-w-[440px] relative z-10">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] p-xl sm:p-margin flex flex-col gap-xl relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-primary-container to-tertiary-container opacity-90"></div>

                <header class="flex flex-col items-center text-center gap-sm mt-xs">
                    @isset($header)
                        <div class="flex flex-col items-center gap-xs">{{ $header }}</div>
                    @else
                        <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $title ?? config('app.name') }}</h1>
                    @endisset
                </header>

                {{ $slot }}

                <div class="flex flex-col items-center gap-xs pt-md border-t border-outline-variant/50">
                    <div class="flex items-center gap-xs text-on-surface-variant">
                        <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">shield</span>
                        <span class="font-body-sm text-body-sm">Your data is encrypted and secure</span>
                    </div>
                </div>
            </div>

            @isset($footer)
                <div class="mt-md text-center">{{ $footer }}</div>
            @endisset
        </div>
    </main>

    <x-site-footer />
</body>
</html>

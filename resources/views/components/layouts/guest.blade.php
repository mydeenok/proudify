@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-head :title="$title" />
</head>
<body class="bg-floor h-dvh overflow-hidden flex flex-col antialiased selection:bg-primary selection:text-on-primary">
    <x-site-header />

    <main class="flex-1 min-h-0 relative overflow-y-auto">
        <div aria-hidden="true" class="absolute inset-0 pointer-events-none overflow-hidden lg:hidden">
            <div class="absolute top-[-12%] left-[-8%] w-[50%] h-[40%] rounded-full bg-primary-fixed opacity-35 blur-[110px]"></div>
            <div class="absolute bottom-[-12%] right-[-8%] w-[50%] h-[40%] rounded-full bg-secondary-fixed opacity-40 blur-[100px]"></div>
        </div>

        <div class="relative z-10 min-h-full lg:min-h-0 lg:h-full max-w-[1100px] mx-auto px-md py-md lg:py-lg flex items-stretch justify-center">
            <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-0 lg:rounded-2xl lg:border lg:border-outline-variant lg:overflow-hidden lg:bg-surface-container-lowest lg:shadow-card lg:min-h-[min(640px,calc(100dvh-72px-2.5rem))]">
                {{-- Brand panel (desktop) --}}
                <aside class="hidden lg:flex flex-col justify-between bg-on-surface text-white p-xl relative overflow-hidden">
                    <div aria-hidden="true" class="absolute inset-0 pointer-events-none">
                        <div class="absolute -top-16 -right-10 w-56 h-56 rounded-full bg-primary-container/40 blur-2xl"></div>
                        <div class="absolute bottom-0 left-0 w-64 h-64 rounded-full bg-secondary/30 blur-3xl"></div>
                        <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>
                    </div>

                    <div class="relative">
                        <p class="font-label-sm text-label-sm text-white/70 uppercase tracking-wider mb-md">{{ config('app.name') }}</p>
                        <h2 class="font-headline-xl text-headline-xl text-white tracking-tight max-w-sm">
                            Certificate designs that look publish-ready.
                        </h2>
                        <p class="mt-md font-body-md text-body-md text-white/75 max-w-sm">
                            Build templates once, issue with confidence, and let recipients verify anything in seconds.
                        </p>
                    </div>

                    <ul class="relative flex flex-col gap-md mt-xl">
                        <li class="flex items-start gap-sm">
                            <span class="mt-0.5 w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">design_services</span>
                            </span>
                            <div>
                                <p class="font-label-md text-label-md text-white">Visual Builder</p>
                                <p class="font-body-sm text-body-sm text-white/65">Lock layout, fill details at issue time.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-sm">
                            <span class="mt-0.5 w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">qr_code_2</span>
                            </span>
                            <div>
                                <p class="font-label-md text-label-md text-white">Instant verification</p>
                                <p class="font-body-sm text-body-sm text-white/65">QR-backed credentials people can trust.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-sm">
                            <span class="mt-0.5 w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-[18px]">groups</span>
                            </span>
                            <div>
                                <p class="font-label-md text-label-md text-white">Bulk issuing</p>
                                <p class="font-body-sm text-body-sm text-white/65">Upload a sheet, send many at once.</p>
                            </div>
                        </li>
                    </ul>
                </aside>

                {{-- Form panel --}}
                <section class="flex flex-col justify-center bg-surface-container-lowest border border-outline-variant lg:border-0 rounded-xl lg:rounded-none shadow-card-sm lg:shadow-none px-lg py-lg sm:px-xl sm:py-xl">
                    <div class="w-full max-w-[400px] mx-auto flex flex-col gap-lg">
                        <header class="flex flex-col items-center lg:items-start text-center lg:text-left gap-xs">
                            @isset($header)
                                {{ $header }}
                            @else
                                <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $title ?? config('app.name') }}</h1>
                            @endisset
                        </header>

                        {{ $slot }}

                        @isset($footer)
                            <div class="text-center lg:text-left">{{ $footer }}</div>
                        @endisset
                    </div>
                </section>
            </div>
        </div>
    </main>
</body>
</html>

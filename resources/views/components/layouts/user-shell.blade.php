@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-head :title="$title" />
</head>
<body class="bg-floor min-h-screen flex flex-col font-body-md text-on-background antialiased">
    <header class="sticky top-0 z-50 bg-surface/95 backdrop-blur-md border-b border-outline-variant shadow-sm">
        <div class="flex justify-between items-center w-full px-xl max-w-[1200px] mx-auto h-[72px]">
            <x-application-logo variant="brand" :href="auth()->check() ? route('dashboard') : '/'" />

            <nav class="hidden md:flex gap-lg items-end h-full pb-0">
                @auth
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Dashboard
                    </x-nav-link>
                    @if (Route::has('certificates.index'))
                        <x-nav-link :href="route('certificates.index')" :active="request()->routeIs('certificates.*')">
                            My Certificates
                        </x-nav-link>
                    @endif
                    @if (Route::has('templates.index'))
                        <x-nav-link :href="route('templates.index')" :active="request()->routeIs('templates.*')">
                            Templates
                        </x-nav-link>
                    @endif
                @endauth
                @if (Route::has('pricing'))
                    <x-nav-link :href="route('pricing')" :active="request()->routeIs('pricing')">
                        Pricing
                    </x-nav-link>
                @endif
                @auth
                    @if (Route::has('subscriptions.index'))
                        <x-nav-link :href="route('subscriptions.index')" :active="request()->routeIs('subscriptions.*')">
                            Subscription
                        </x-nav-link>
                    @endif
                @endauth
            </nav>

            <div class="flex items-center gap-sm">
                @auth
                    <button type="button" class="p-xs text-on-surface-variant hover:text-primary transition-colors rounded-full hover:bg-surface-variant" aria-label="Notifications">
                        <span class="material-symbols-outlined">notifications</span>
                    </button>

                    <div class="relative" x-data="{ open: false }">
                        <button type="button" @click="open = !open" @click.outside="open = false" class="flex items-center gap-xs" aria-label="Account menu">
                            <div class="w-10 h-10 rounded-full bg-surface-variant border border-outline-variant flex items-center justify-center font-label-md text-label-md text-on-surface-variant">
                                {{ Str::of(auth()->user()->first_name)->substr(0, 1) }}{{ Str::of(auth()->user()->last_name)->substr(0, 1) }}
                            </div>
                        </button>
                        <div x-show="open" x-cloak class="absolute right-0 mt-sm w-48 bg-surface-container-lowest border border-outline-variant rounded-lg shadow-lg py-xs z-50">
                            <a href="{{ route('profile.edit') }}" class="block px-md py-sm font-body-sm text-body-sm text-on-surface hover:bg-surface-container-low">Account Settings</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-md py-sm font-body-sm text-body-sm text-on-surface hover:bg-surface-container-low">Log Out</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="hidden md:inline-flex font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors">Login</a>
                    <a href="{{ route('register') }}" class="btn-primary h-10 px-md text-sm">
                        Get Started
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main class="flex-grow w-full max-w-[1200px] mx-auto px-margin py-2xl">
        @if (session('status'))
            <div class="mb-lg alert-success">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </main>

    <x-site-footer />
</body>
</html>

@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <x-head :title="$title" suffix="Admin" />
</head>
<body class="bg-floor h-full font-body-md text-on-background antialiased flex" x-data="{ mobileNavOpen: false }">
    <nav class="hidden md:flex flex-col h-screen w-[280px] bg-surface-container-low shadow-md p-md gap-xs shrink-0 sticky top-0">
        <div class="mb-xl px-sm pt-sm">
            <x-application-logo variant="sidebar" :href="route('admin.dashboard')" />
        </div>

        <div class="flex-1 flex flex-col gap-base">
            <x-admin-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="dashboard">
                Dashboard
            </x-admin-nav-link>
            @if (Route::has('admin.certificates.index'))
                <x-admin-nav-link :href="route('admin.certificates.index')" :active="request()->routeIs('admin.certificates.*')" icon="workspace_premium">
                    Certificates
                </x-admin-nav-link>
            @endif
            @if (Route::has('bulk-upload.history'))
                <x-admin-nav-link :href="route('bulk-upload.history')" :active="request()->routeIs('bulk-upload.*') || request()->routeIs('admin.bulk-upload.*')" icon="upload_file">
                    Bulk Uploads
                </x-admin-nav-link>
            @endif
            @if (Route::has('admin.templates.index'))
                <x-admin-nav-link :href="route('admin.templates.index')" :active="request()->routeIs('admin.templates.*')" icon="layers">
                    Templates
                </x-admin-nav-link>
            @endif
            <x-admin-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="group">
                Users
            </x-admin-nav-link>
            @if (Route::has('admin.billing-settings.edit'))
                <x-admin-nav-link :href="route('admin.billing-settings.edit')" :active="request()->routeIs('admin.billing-settings.*')" icon="payments">
                    Billing Settings
                </x-admin-nav-link>
            @endif
            @if (Route::has('admin.certificate-orders.index'))
                <x-admin-nav-link :href="route('admin.certificate-orders.index')" :active="request()->routeIs('admin.certificate-orders.*')" icon="receipt_long">
                    Certificate Orders
                </x-admin-nav-link>
            @endif
            @if (Route::has('admin.api-tokens.index'))
                <x-admin-nav-link :href="route('admin.api-tokens.index')" :active="request()->routeIs('admin.api-tokens.*')" icon="key">
                    API Keys
                </x-admin-nav-link>
            @endif
            {{-- Subscription Plans / User Subscriptions nav hidden for now
                 — billing moved to pay-per-certificate (see Billing
                 Settings / Certificate Orders above); these routes still
                 work, just unlinked. --}}
            @if (Route::has('admin.analytics.index'))
                <x-admin-nav-link :href="route('admin.analytics.index')" :active="request()->routeIs('admin.analytics.*')" icon="monitoring">
                    Analytics
                </x-admin-nav-link>
            @endif
            @if (Route::has('admin.contact-requests.index'))
                <x-admin-nav-link :href="route('admin.contact-requests.index')" :active="request()->routeIs('admin.contact-requests.*')" icon="mail">
                    Contact Requests
                </x-admin-nav-link>
            @endif
        </div>

        <div class="mt-auto pt-md">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full bg-surface border border-outline-variant text-on-surface font-label-md text-label-md h-11 rounded-lg flex items-center justify-center gap-sm hover:bg-surface-container-highest transition-colors">
                    <span class="material-symbols-outlined text-[20px]">logout</span>
                    Log Out
                </button>
            </form>
        </div>
    </nav>

    <main class="flex-1 overflow-y-auto bg-background relative w-full">
        <div class="md:hidden flex justify-between items-center w-full px-md h-[72px] bg-surface border-b border-outline-variant sticky top-0 z-20 shadow-sm">
            <x-application-logo variant="brand" :href="route('admin.dashboard')" />
            <button type="button" @click="mobileNavOpen = !mobileNavOpen" class="p-2 text-on-surface-variant" aria-label="Toggle navigation">
                <span class="material-symbols-outlined">menu</span>
            </button>
        </div>

        <div x-show="mobileNavOpen" x-cloak class="md:hidden fixed inset-0 z-30 bg-black/40" @click="mobileNavOpen = false">
            <nav class="absolute left-0 top-0 bottom-0 w-[280px] bg-surface-container-low p-md flex flex-col gap-xs shadow-xl" @click.stop>
                <div class="mb-lg px-sm pt-sm">
                    <x-application-logo variant="sidebar" :href="route('admin.dashboard')" />
                </div>
                <x-admin-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" icon="dashboard">Dashboard</x-admin-nav-link>
                @if (Route::has('admin.certificates.index'))
                    <x-admin-nav-link :href="route('admin.certificates.index')" :active="request()->routeIs('admin.certificates.*')" icon="workspace_premium">Certificates</x-admin-nav-link>
                @endif
                @if (Route::has('bulk-upload.history'))
                    <x-admin-nav-link :href="route('bulk-upload.history')" :active="request()->routeIs('bulk-upload.*') || request()->routeIs('admin.bulk-upload.*')" icon="upload_file">Bulk Uploads</x-admin-nav-link>
                @endif
                @if (Route::has('admin.templates.index'))
                    <x-admin-nav-link :href="route('admin.templates.index')" :active="request()->routeIs('admin.templates.*')" icon="layers">Templates</x-admin-nav-link>
                @endif
                <x-admin-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="group">Users</x-admin-nav-link>
                @if (Route::has('admin.contact-requests.index'))
                    <x-admin-nav-link :href="route('admin.contact-requests.index')" :active="request()->routeIs('admin.contact-requests.*')" icon="mail">Contact Requests</x-admin-nav-link>
                @endif
            </nav>
        </div>

        <div class="max-w-[1440px] mx-auto px-md md:px-margin py-xl md:py-2xl">
            @if (session('status'))
                <div class="mb-lg px-md py-sm rounded-lg bg-emerald-500/10 text-emerald-700 font-body-sm text-body-sm border border-emerald-500/20">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </div>
    </main>

    {{-- top offset clears the sticky mobile header (72px) below md; desktop has no header bar to clear --}}
    <div class="fixed top-[88px] md:top-md right-md z-40">
        <livewire:notification-bell />
    </div>
</body>
</html>

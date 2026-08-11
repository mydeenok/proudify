<section>
    <header class="flex items-center justify-between gap-md mb-md">
        <div>
            <h2 class="font-headline-md text-headline-md text-on-surface">Active Sessions</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">
                Devices currently signed in to your account.
            </p>
        </div>
        <button
            type="button"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-logout-other-devices')"
            class="h-10 px-md rounded-lg border border-error/30 text-error font-label-md text-label-md hover:bg-error/5 transition-colors shrink-0"
        >
            Log out other devices
        </button>
    </header>

    @if (session('status') === 'session-revoked')
        <div class="mb-md bg-emerald-50 border border-emerald-200 rounded-lg px-md py-sm font-body-sm text-body-sm text-emerald-800">
            That device has been signed out.
        </div>
    @elseif (session('status') === 'other-sessions-revoked')
        <div class="mb-md bg-emerald-50 border border-emerald-200 rounded-lg px-md py-sm font-body-sm text-body-sm text-emerald-800">
            All other devices have been signed out.
        </div>
    @endif

    <div class="border border-outline-variant rounded-lg divide-y divide-outline-variant overflow-hidden">
        @foreach ($activeSessions as $session)
            <div class="flex items-center justify-between gap-md px-md py-sm">
                <div class="flex items-center gap-sm min-w-0">
                    <div class="w-9 h-9 rounded-full bg-surface-container-high text-on-surface-variant flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[18px]">{{ str_contains($session['device'], 'iPhone') || str_contains($session['device'], 'Android') ? 'smartphone' : 'computer' }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="font-label-md text-label-md text-on-surface truncate">
                            {{ $session['device'] }}
                            @if ($session['is_current'])
                                <span class="font-label-sm text-label-sm text-emerald-700">&middot; This device</span>
                            @endif
                        </p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">
                            {{ $session['ip_address'] ?? 'Unknown IP' }} &middot; active {{ $session['last_active']->diffForHumans() }}
                        </p>
                    </div>
                </div>
                @unless ($session['is_current'])
                    <form method="POST" action="{{ route('profile.sessions.destroy', $session['id']) }}" onsubmit="return confirm('Sign out this device?');" class="shrink-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="h-8 px-3 text-[11px] font-medium text-error bg-surface border border-error/30 rounded-md hover:bg-error/5 transition-colors">Sign out</button>
                    </form>
                @endunless
            </div>
        @endforeach
    </div>

    {{-- Password confirmation for the bulk "log out other devices" action --}}
    <x-modal name="confirm-logout-other-devices" :show="$errors->logoutOtherDevices->isNotEmpty()" focusable>
        <form method="POST" action="{{ route('profile.sessions.destroy-others') }}" class="p-lg">
            @csrf
            @method('DELETE')
            <h2 class="font-headline-md text-headline-md text-on-surface">Log out other devices</h2>
            <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                Enter your password to confirm. This won't sign out this device.
            </p>
            <div class="mt-lg">
                <x-input-label for="logout_password" value="Password" class="sr-only" />
                <x-text-input id="logout_password" name="password" type="password" placeholder="Password" autocomplete="current-password" />
                <x-input-error :messages="$errors->logoutOtherDevices->get('password')" />
            </div>
            <div class="mt-lg flex justify-end gap-sm">
                <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                <x-danger-button>Log Out Other Devices</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>

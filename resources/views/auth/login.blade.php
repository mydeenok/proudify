<x-layouts.guest title="Log in">
    <x-slot:header>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Welcome back</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Sign in to issue and manage certificates</p>
    </x-slot:header>

    <x-auth-session-status class="mb-lg" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-lg">
        @csrf

        <div class="flex flex-col gap-base">
            <x-input-label for="email" value="Email address" />
            <x-icon-input id="email" name="email" type="email" icon="mail" required autofocus autocomplete="username" :value="old('email')" placeholder="name@company.com" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="flex flex-col gap-base">
            <div class="flex justify-between items-center">
                <x-input-label for="password" value="Password" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="font-label-sm text-label-sm text-secondary hover:underline transition-all">Forgot password?</a>
                @endif
            </div>
            <x-icon-input id="password" name="password" type="password" icon="lock" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label for="remember_me" class="inline-flex items-center gap-xs">
            <input id="remember_me" type="checkbox" name="remember" class="rounded border-outline-variant text-secondary focus:ring-secondary">
            <span class="font-body-sm text-body-sm text-on-surface-variant">Remember me</span>
        </label>

        <x-primary-button class="w-full mt-xs">
            Sign in
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </x-primary-button>
    </form>

    <div class="flex items-center gap-md">
        <div class="h-px bg-outline-variant flex-1"></div>
        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest">Or</span>
        <div class="h-px bg-outline-variant flex-1"></div>
    </div>

    <div class="flex flex-col gap-sm">
        <a href="{{ route('sso.redirect', 'Google') }}" class="w-full h-11 flex items-center justify-center gap-sm border border-outline-variant bg-surface hover:bg-surface-container-low text-on-surface font-label-md text-label-md rounded-lg transition-colors">
            <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Continue with Google
        </a>
        <a href="{{ route('sso.redirect', 'Facebook') }}" class="w-full h-11 flex items-center justify-center gap-sm border border-outline-variant bg-surface hover:bg-surface-container-low text-on-surface font-label-md text-label-md rounded-lg transition-colors">
            <svg class="w-5 h-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#1877F2" d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
            Continue with Facebook
        </a>
    </div>

    <p class="text-center font-body-md text-body-md text-on-surface-variant">
        Don't have an account?
        <a href="{{ route('register') }}" class="text-primary font-semibold hover:underline">Get started</a>
    </p>
</x-layouts.guest>

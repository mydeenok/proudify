<x-layouts.guest title="Log in">
    <x-slot:header>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Welcome back</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Sign in to your account</p>
    </x-slot:header>

    <x-auth-session-status class="mb-lg" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-lg">
        @csrf

        <div class="flex flex-col gap-base">
            <x-input-label for="email" value="Email Address" />
            <x-icon-input id="email" name="email" type="email" icon="mail" required autofocus autocomplete="username" :value="old('email')" placeholder="name@company.com" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="flex flex-col gap-base">
            <div class="flex justify-between items-center">
                <x-input-label for="password" value="Password" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="font-label-sm text-label-sm text-primary hover:underline transition-all">Forgot password?</a>
                @endif
            </div>
            <x-icon-input id="password" name="password" type="password" icon="lock" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label for="remember_me" class="inline-flex items-center gap-xs">
            <input id="remember_me" type="checkbox" name="remember" class="rounded border-outline-variant text-primary focus:ring-primary">
            <span class="font-body-sm text-body-sm text-on-surface-variant">Remember me</span>
        </label>

        <x-primary-button class="w-full mt-xs">
            Sign In
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </x-primary-button>
    </form>

    <div class="flex items-center gap-md">
        <div class="h-px bg-outline-variant flex-1"></div>
        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest">Or</span>
        <div class="h-px bg-outline-variant flex-1"></div>
    </div>

    <div class="flex flex-col gap-sm">
        <a href="{{ route('sso.redirect', 'Google') }}" class="w-full h-11 flex items-center justify-center gap-sm border border-outline-variant bg-surface-container-lowest hover:bg-surface-container-low text-on-surface font-label-md text-label-md rounded-lg transition-colors">
            <span class="material-symbols-outlined text-on-surface-variant text-[18px]">language</span>
            Continue with Google
        </a>
        <a href="{{ route('sso.redirect', 'Facebook') }}" class="w-full h-11 flex items-center justify-center gap-sm border border-outline-variant bg-surface-container-lowest hover:bg-surface-container-low text-on-surface font-label-md text-label-md rounded-lg transition-colors">
            <span class="material-symbols-outlined text-on-surface-variant text-[18px]">thumb_up</span>
            Continue with Facebook
        </a>
    </div>

    <p class="text-center font-body-md text-body-md text-on-surface-variant">
        Don't have an account?
        <a href="{{ route('register') }}" class="text-primary font-bold hover:underline transition-all">Register here</a>
    </p>
</x-layouts.guest>

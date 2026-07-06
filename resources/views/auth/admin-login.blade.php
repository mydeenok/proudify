<x-layouts.guest title="Admin Log in">
    <x-slot:header>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Admin Console</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Sign in with your Proudify staff account.</p>
    </x-slot:header>

    <x-auth-session-status class="mb-lg" :status="session('status')" />

    <form method="POST" action="{{ route('admin.login') }}" class="flex flex-col gap-lg">
        @csrf

        <div class="flex flex-col gap-base">
            <x-input-label for="email" value="Email Address" />
            <x-icon-input id="email" name="email" type="email" icon="mail" required autofocus autocomplete="username" :value="old('email')" placeholder="admin@proudify.test" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="flex flex-col gap-base">
            <x-input-label for="password" value="Password" />
            <x-icon-input id="password" name="password" type="password" icon="lock" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <x-primary-button class="w-full">
            Sign In
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </x-primary-button>
    </form>

    <p class="text-center font-body-md text-body-md text-on-surface-variant">
        Not a staff account?
        <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">Go to standard login</a>
    </p>
</x-layouts.guest>

<x-layouts.guest title="Forgot password">
    <x-slot:header>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Forgot your password?</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Enter your email and we'll send you a reset link.</p>
    </x-slot:header>

    <x-auth-session-status class="mb-lg" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-lg">
        @csrf

        <div class="flex flex-col gap-base">
            <x-input-label for="email" value="Email Address" />
            <x-icon-input id="email" name="email" type="email" icon="mail" required autofocus :value="old('email')" placeholder="name@company.com" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <x-primary-button class="w-full">
            Email Password Reset Link
            <span class="material-symbols-outlined text-[18px]">send</span>
        </x-primary-button>
    </form>

    <p class="text-center font-body-md text-body-md text-on-surface-variant">
        <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">Back to login</a>
    </p>
</x-layouts.guest>

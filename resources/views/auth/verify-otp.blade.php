<x-layouts.guest title="Verify your email">
    <x-slot:header>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Check your email</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
            We sent a 6-digit code to <span class="font-semibold text-on-surface">{{ $email }}</span>
        </p>
    </x-slot:header>

    <x-auth-session-status class="mb-lg" :status="session('status')" />

    @isset($debugCode)
        <div class="mb-lg px-md py-sm rounded-lg border border-dashed border-tertiary bg-tertiary-container/10 text-center">
            <p class="font-label-sm text-label-sm text-tertiary uppercase tracking-wide">Local dev only — mail isn't configured</p>
            <p class="font-headline-md text-headline-md text-on-surface tracking-[0.3em] mt-1">{{ $debugCode }}</p>
        </div>
    @endisset

    <form method="POST" action="{{ route('otp.verify.store') }}" class="flex flex-col gap-lg">
        @csrf

        <div class="flex flex-col gap-base">
            <x-input-label for="otp_code" value="Verification code" />
            <x-text-input
                id="otp_code"
                name="otp_code"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                autocomplete="one-time-code"
                required
                autofocus
                class="text-center tracking-[0.5em] font-headline-md text-headline-md"
                placeholder="000000"
            />
            <x-input-error :messages="$errors->get('otp_code')" />
        </div>

        <x-primary-button class="w-full">
            Verify
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
        </x-primary-button>
    </form>

    <form method="POST" action="{{ route('otp.resend') }}" class="text-center">
        @csrf
        <button type="submit" class="font-body-sm text-body-sm text-primary hover:underline font-medium">
            Didn't get a code? Resend it
        </button>
    </form>
</x-layouts.guest>

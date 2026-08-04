<x-layouts.app title="Contact" :show-status="false">
    <div class="max-w-3xl mx-auto w-full">
        <div class="mb-xl">
            <h1 class="font-headline-xl text-headline-xl text-on-surface">Contact support</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-xs">
                Questions about certificates, billing, or your account? Send a message and our team will follow up.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-lg px-md py-sm rounded-lg bg-emerald-500/10 text-emerald-700 font-body-sm text-body-sm border border-emerald-500/20">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('contact.store') }}" class="card-surface shadow-card-sm p-lg flex flex-col gap-lg">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-lg">
                <div class="flex flex-col gap-base">
                    <x-input-label for="name" value="Your name" />
                    <x-text-input id="name" name="name" type="text" required maxlength="120" :value="$name" autofocus />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div class="flex flex-col gap-base">
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" required maxlength="255" :value="$email" autocomplete="email" />
                    <x-input-error :messages="$errors->get('email')" />
                </div>
            </div>

            <div class="flex flex-col gap-base">
                <x-input-label for="organization" value="Organization (optional)" />
                <x-text-input id="organization" name="organization" type="text" maxlength="160" :value="$organization" />
                <x-input-error :messages="$errors->get('organization')" />
            </div>

            <div class="flex flex-col gap-base">
                <x-input-label for="subject" value="Subject" />
                <x-text-input id="subject" name="subject" type="text" required maxlength="160" :value="$subject ?? old('subject')" placeholder="Billing question, account help…" />
                <x-input-error :messages="$errors->get('subject')" />
            </div>

            <div class="flex flex-col gap-base">
                <x-input-label for="message" value="Message" />
                <textarea
                    id="message"
                    name="message"
                    required
                    maxlength="5000"
                    rows="7"
                    class="form-input h-auto py-sm min-h-[160px]"
                    placeholder="Tell us what you need help with…"
                >{{ old('message') }}</textarea>
                <x-input-error :messages="$errors->get('message')" />
            </div>

            <div class="flex justify-end">
                <x-primary-button>
                    Send message
                    <span class="material-symbols-outlined text-[18px]">send</span>
                </x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.app>

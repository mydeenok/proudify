<x-layouts.app title="Contact" :show-status="false">
    <div class="max-w-5xl mx-auto w-full">
        <x-page-header
            title="Contact support"
            description="Questions about certificates, billing, or your account? Send a message and we will follow up."
            class="mb-xl"
        />

        @if (session('status'))
            <div class="mb-lg alert-success flex items-start gap-sm">
                <span class="material-symbols-outlined text-[20px] shrink-0" style="font-variation-settings: 'FILL' 1">check_circle</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter items-start">
            <aside class="lg:col-span-4 flex flex-col gap-md">
                <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg">
                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-md">How we can help</p>
                    <ul class="flex flex-col gap-md">
                        <li class="flex gap-sm">
                            <span class="material-symbols-outlined text-primary text-[22px] shrink-0">workspace_premium</span>
                            <div>
                                <p class="font-label-md text-label-md text-on-surface">Certificates &amp; templates</p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Issuing, Visual Builder, bulk upload, verification.</p>
                            </div>
                        </li>
                        <li class="flex gap-sm">
                            <span class="material-symbols-outlined text-primary text-[22px] shrink-0">payments</span>
                            <div>
                                <p class="font-label-md text-label-md text-on-surface">Billing &amp; plans</p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Subscriptions, invoices, and quota questions.</p>
                            </div>
                        </li>
                        <li class="flex gap-sm">
                            <span class="material-symbols-outlined text-primary text-[22px] shrink-0">manage_accounts</span>
                            <div>
                                <p class="font-label-md text-label-md text-on-surface">Account access</p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Login, organization profile, team permissions.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="bg-surface-container-low border border-outline-variant rounded-xl p-md flex gap-sm">
                    <span class="material-symbols-outlined text-secondary text-[22px] shrink-0">schedule</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface">Typical reply time</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">Within 1 business day on weekdays.</p>
                    </div>
                </div>
            </aside>

            <div class="lg:col-span-8">
                <form method="POST" action="{{ route('contact.store') }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl p-lg sm:p-xl flex flex-col gap-lg shadow-card-sm">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                        <div class="flex flex-col gap-base">
                            <x-input-label for="name" value="Your name" />
                            <x-icon-input id="name" name="name" type="text" icon="person" required maxlength="120" :value="$name" autofocus autocomplete="name" placeholder="Ada Lovelace" />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>
                        <div class="flex flex-col gap-base">
                            <x-input-label for="email" value="Email" />
                            <x-icon-input id="email" name="email" type="email" icon="mail" required maxlength="255" :value="$email" autocomplete="email" placeholder="you@company.com" />
                            <x-input-error :messages="$errors->get('email')" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-base">
                        <x-input-label for="organization" value="Organization (optional)" />
                        <x-icon-input id="organization" name="organization" type="text" icon="apartment" maxlength="160" :value="$organization" placeholder="Your company or school" />
                        <x-input-error :messages="$errors->get('organization')" />
                    </div>

                    <div class="flex flex-col gap-base">
                        <x-input-label for="subject" value="Subject" />
                        <x-icon-input id="subject" name="subject" type="text" icon="topic" required maxlength="160" :value="$subject ?? old('subject')" placeholder="Billing question, account help…" />
                        <x-input-error :messages="$errors->get('subject')" />
                    </div>

                    <div class="flex flex-col gap-base">
                        <x-input-label for="message" value="Message" />
                        <textarea
                            id="message"
                            name="message"
                            required
                            maxlength="5000"
                            rows="6"
                            class="form-input h-auto py-sm min-h-[140px] resize-y"
                            placeholder="Tell us what you need help with…"
                        >{{ old('message') }}</textarea>
                        <x-input-error :messages="$errors->get('message')" />
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-md pt-xs border-t border-outline-variant">
                        <p class="font-body-sm text-body-sm text-on-surface-variant">
                            We only use this to reply to your request.
                        </p>
                        <x-primary-button class="btn-primary w-full sm:w-auto justify-center">
                            Send message
                            <span class="material-symbols-outlined text-[18px]">send</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>

<section>
    <header>
        <h2 class="font-headline-md text-headline-md text-on-surface">Organization Branding</h2>
        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
            Logos and your signature appear on every certificate you issue.
        </p>
    </header>

    <form method="post" action="{{ route('profile.organization.update') }}" enctype="multipart/form-data" class="mt-lg space-y-lg">
        @csrf

        <div>
            <x-input-label value="Logos" />

            @if (! empty($user->org_logos))
                <div class="flex flex-wrap gap-md mt-sm mb-sm">
                    @foreach ($user->org_logos as $logoIndex => $logoPath)
                        <div class="relative w-20 h-20 rounded-lg border border-outline-variant overflow-hidden bg-surface-container-highest">
                            <img src="{{ route('profile.organization.logo', ['index' => $logoIndex]) }}" alt="Organization logo" class="w-full h-full object-contain">
                            <button
                                type="submit"
                                name="remove_logos[]"
                                value="{{ $logoPath }}"
                                class="absolute top-1 right-1 inline-flex items-center justify-center h-6 px-1.5 rounded bg-error text-on-error text-[10px] font-semibold shadow-sm hover:opacity-90"
                                title="Remove this logo"
                            >
                                Remove
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            <input type="file" name="org_logos[]" multiple accept="image/*" class="block w-full text-body-sm font-body-sm text-on-surface-variant">
            <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-xs">Up to 5 logos total. PNG or JPG, max 2MB each.</p>
            <x-input-error :messages="$errors->get('org_logos.*')" />
        </div>

        <div>
            <x-input-label value="Signature" />

            @if ($user->signature_path)
                <img src="{{ route('profile.organization.signature') }}" alt="Signature" class="h-16 mt-sm mb-sm bg-surface-container-highest rounded p-2">
            @endif

            <input type="file" name="signature" accept="image/*" class="block w-full text-body-sm font-body-sm text-on-surface-variant">
            <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-xs">Uploading a new signature replaces the current one. PNG or JPG, max 1MB.</p>
            <x-input-error :messages="$errors->get('signature')" />
        </div>

        <div class="flex items-center gap-md">
            <x-primary-button>Save Branding</x-primary-button>

            @if (session('status') === 'organization-updated')
                <x-form-status status="Branding updated." />
            @elseif (session('status') === 'organization-update-failed')
                <x-form-status status="Couldn't save your branding. Please try again." variant="error" />
            @endif
        </div>
    </form>
</section>

@php
    // Validation errors and post-save status land in session/error bags that
    // are keyed by form, not by tab - without this, a failure on the
    // Security or Organization tab is invisible, since the Profile tab is
    // always the one shown right after the redirect back.
    $activeTab = 'profile';

    if (
        $errors->updatePassword->any()
        || $errors->userDeletion->any()
        || $errors->logoutOtherDevices->any()
        || in_array(session('status'), ['password-updated', 'session-revoked', 'other-sessions-revoked'], true)
    ) {
        $activeTab = 'security';
    } elseif (
        collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'org_logos') || str_starts_with($key, 'signature') || str_starts_with($key, 'remove_logos'))
        || in_array(session('status'), ['organization-updated', 'organization-update-failed'], true)
    ) {
        $activeTab = 'organization';
    } elseif ($errors->default->has('name') || in_array(session('status'), ['api-token-created', 'api-token-revoked'], true)) {
        $activeTab = 'api';
    }
@endphp

<x-layouts.user-shell title="Account Settings">
    <x-page-header
        title="Account Settings"
        description="Manage your profile, organization details, and security preferences."
    />

    <div x-data="{ tab: @js($activeTab) }">
    <div class="flex gap-xl mb-xl border-b border-outline-variant overflow-x-auto hide-scrollbar">
        <button type="button" @click="tab = 'profile'" :class="tab === 'profile' ? 'nav-tab-active' : 'nav-tab'" class="pb-sm whitespace-nowrap">Profile</button>
        <button type="button" @click="tab = 'security'" :class="tab === 'security' ? 'nav-tab-active' : 'nav-tab'" class="pb-sm whitespace-nowrap">Security</button>
        <button type="button" @click="tab = 'organization'" :class="tab === 'organization' ? 'nav-tab-active' : 'nav-tab'" class="pb-sm whitespace-nowrap">Organization</button>
        <button type="button" @click="tab = 'api'" :class="tab === 'api' ? 'nav-tab-active' : 'nav-tab'" class="pb-sm whitespace-nowrap">API Access</button>
    </div>

    <div x-show="tab === 'profile'" class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
        <div class="col-span-1 md:col-span-6 card-surface p-lg shadow-card">
            <div class="flex items-center gap-md mb-lg pb-lg border-b border-outline-variant">
                <div class="w-16 h-16 rounded-full bg-primary-container text-on-primary-container flex items-center justify-center font-headline-lg text-headline-lg font-bold">
                    {{ strtoupper(substr(auth()->user()->first_name, 0, 1).substr(auth()->user()->last_name, 0, 1)) }}
                </div>
                <div>
                    <p class="font-headline-md text-headline-md text-on-surface">{{ auth()->user()->name }}</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ auth()->user()->email }}</p>
                </div>
            </div>
            @include('profile.partials.update-profile-information-form')
        </div>
        <div class="col-span-1 md:col-span-6 card-surface p-lg shadow-card">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Connected Accounts</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-lg">Single sign-on integrations will appear here when enabled for your organization.</p>
            <div class="flex items-center gap-md p-md rounded-lg border border-outline-variant bg-surface-container-low">
                <span class="material-symbols-outlined text-on-surface-variant">link_off</span>
                <span class="font-body-sm text-body-sm text-on-surface-variant">No connected accounts</span>
            </div>
        </div>
    </div>

    <div x-show="tab === 'security'" class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
        <div class="col-span-1 md:col-span-6 card-surface p-lg shadow-card">
            @include('profile.partials.update-password-form')
        </div>
        <div class="col-span-1 md:col-span-6 card-surface p-lg shadow-card border-error/30">
            @include('profile.partials.delete-user-form')
        </div>
        <div class="col-span-1 md:col-span-12 card-surface p-lg shadow-card">
            @include('profile.partials.active-sessions')
        </div>
    </div>

    <div x-show="tab === 'organization'" class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
        <div class="col-span-1 md:col-span-8 card-surface p-lg shadow-card">
            @include('profile.partials.update-organization-form')
        </div>
        <div class="col-span-1 md:col-span-4 card-surface p-lg shadow-card">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Digital Signature</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant mb-md">Your signature appears on issued certificates when configured in templates.</p>
            @if (auth()->user()->signature_path)
                <div class="aspect-[3/1] rounded-lg border border-outline-variant bg-surface-container-low flex items-center justify-center p-md">
                    <img src="{{ route('profile.organization.signature') }}" alt="Your signature" class="max-h-full max-w-full object-contain">
                </div>
            @else
                <div class="aspect-[3/1] rounded-lg border-2 border-dashed border-outline-variant bg-surface-container-low flex items-center justify-center">
                    <span class="font-body-md text-body-md text-on-surface-variant italic">No signature uploaded yet</span>
                </div>
            @endif
        </div>
    </div>

    <div x-show="tab === 'api'" class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
        @include('profile.partials.update-api-tokens-form')
    </div>
    </div>
</x-layouts.user-shell>

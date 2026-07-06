<x-layouts.user-shell title="Account Settings">
    <x-page-header
        title="Account Settings"
        description="Manage your profile, organization details, and security preferences."
    />

    <div id="settings-tabs" class="flex gap-xl mb-xl border-b border-outline-variant overflow-x-auto hide-scrollbar">
        <button type="button" data-tab="profile" class="nav-tab-active pb-sm whitespace-nowrap">Profile</button>
        <button type="button" data-tab="security" class="nav-tab pb-sm whitespace-nowrap">Security</button>
        <button type="button" data-tab="organization" class="nav-tab pb-sm whitespace-nowrap">Organization</button>
    </div>

    <div data-tab-panel="profile" class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
        <div class="col-span-1 md:col-span-6 card-surface p-lg bento-shadow">
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
        <div class="col-span-1 md:col-span-6 card-surface p-lg bento-shadow">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Connected Accounts</h3>
            <p class="font-body-md text-body-md text-on-surface-variant mb-lg">Single sign-on integrations will appear here when enabled for your organization.</p>
            <div class="flex items-center gap-md p-md rounded-lg border border-outline-variant bg-surface-container-low">
                <span class="material-symbols-outlined text-on-surface-variant">link_off</span>
                <span class="font-body-sm text-body-sm text-on-surface-variant">No connected accounts</span>
            </div>
        </div>
    </div>

    <div data-tab-panel="security" class="hidden grid grid-cols-1 md:grid-cols-12 gap-gutter">
        <div class="col-span-1 md:col-span-6 card-surface p-lg bento-shadow">
            @include('profile.partials.update-password-form')
        </div>
        <div class="col-span-1 md:col-span-6 card-surface p-lg bento-shadow border-error/30">
            @include('profile.partials.delete-user-form')
        </div>
    </div>

    <div data-tab-panel="organization" class="hidden grid grid-cols-1 md:grid-cols-12 gap-gutter">
        <div class="col-span-1 md:col-span-8 card-surface p-lg bento-shadow">
            @include('profile.partials.update-organization-form')
        </div>
        <div class="col-span-1 md:col-span-4 card-surface p-lg bento-shadow">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Digital Signature</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant mb-md">Your signature appears on issued certificates when configured in templates.</p>
            <div class="aspect-[3/1] rounded-lg border-2 border-dashed border-outline-variant bg-surface-container-low flex items-center justify-center">
                <span class="font-body-md text-body-md text-on-surface-variant italic">Signature preview</span>
            </div>
        </div>
    </div>
</x-layouts.user-shell>

<x-layouts.admin-shell title="Subscription Plans">
    <x-page-header
        title="Subscription Plans"
        description="Manage your product offerings, pricing, and feature limits."
    >
        <x-slot:actions>
            <a href="{{ route('admin.subscriptions.create') }}" wire:navigate class="btn-primary h-11">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Create Plan
            </a>
        </x-slot:actions>
    </x-page-header>

    <livewire:admin.subscription-plans-table />
</x-layouts.admin-shell>

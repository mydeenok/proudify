<x-layouts.admin-shell title="Users">
    <x-page-header
        title="User Management"
        description="Manage platform access, roles, and subscriptions."
    >
        <x-slot:actions>
            <button type="button" class="btn-secondary h-10" disabled title="Coming soon">
                <span class="material-symbols-outlined text-[18px]">download</span>
                Export
            </button>
        </x-slot:actions>
    </x-page-header>

    <livewire:admin.users-table />
</x-layouts.admin-shell>

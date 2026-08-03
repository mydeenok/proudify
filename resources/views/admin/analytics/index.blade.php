<x-layouts.admin-shell title="Analytics">
    <x-page-header
        title="Analytics Overview"
        description="Track platform performance and issuance metrics."
    >
        <x-slot:actions>
            <button type="button" class="btn-secondary h-10" disabled title="Coming soon">
                <span class="material-symbols-outlined text-[20px]">download</span>
                Export
            </button>
        </x-slot:actions>
    </x-page-header>

    <livewire:admin.analytics-dashboard />
</x-layouts.admin-shell>

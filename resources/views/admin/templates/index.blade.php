<x-layouts.admin-shell title="Templates">
    <x-page-header
        title="Template Library"
        description="Manage and configure credential designs across the platform."
    >
        <x-slot:actions>
            <a href="{{ route('admin.templates.create') }}" wire:navigate class="btn-primary h-11">
                <span class="material-symbols-outlined text-[20px]">add</span>
                New Template
            </a>
        </x-slot:actions>
    </x-page-header>

    <livewire:admin.templates-table />
</x-layouts.admin-shell>

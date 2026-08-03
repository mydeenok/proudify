<x-layouts.user-shell title="My Certificates">
    <x-page-header
        title="My Certificates"
        description="Manage and track all issued credentials."
    >
        <x-slot:actions>
            @if (Route::has('bulk-upload.select-template'))
                <a href="{{ route('bulk-upload.select-template') }}" wire:navigate class="btn-secondary">
                    <span class="material-symbols-outlined text-[18px]">upload_file</span>
                    Bulk Upload
                </a>
            @endif
            <a href="{{ route('templates.index') }}" wire:navigate class="btn-primary">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Create Certificate
            </a>
        </x-slot:actions>
    </x-page-header>

    <livewire:certificates-index />
</x-layouts.user-shell>

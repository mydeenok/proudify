<x-layouts.admin-shell title="Certificates">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-xl">
        <div class="flex flex-col gap-xs">
            <nav class="flex items-center gap-xs font-label-sm text-label-sm text-on-surface-variant">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:text-primary transition-colors">Home</a>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface">Certificates Management</span>
            </nav>
            <h1 class="font-headline-xl text-headline-xl text-on-surface">Certificates Registry</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Manage, verify, and issue credentials across all educational cohorts.</p>
        </div>
        <div class="flex items-center gap-sm shrink-0">
            <button type="submit" form="bulk-download-form" id="btn-download-selected" class="btn-secondary h-11 opacity-50 cursor-not-allowed" disabled>
                <span class="material-symbols-outlined text-[20px]">download</span>
                Download Selected (ZIP)
            </button>
            @if (Route::has('admin.bulk-upload.create'))
            <a href="{{ route('admin.bulk-upload.create') }}" wire:navigate class="btn-secondary h-11">
                <span class="material-symbols-outlined text-[20px]">upload_file</span>
                Bulk Upload
            </a>
            @endif
            <a href="{{ route('templates.index') }}" wire:navigate class="btn-primary h-11">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Create Certificate
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.certificates.bulk-download') }}" id="bulk-download-form">
        @csrf
    </form>

    <livewire:admin.certificates-table />
</x-layouts.admin-shell>
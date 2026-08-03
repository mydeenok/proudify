@if (auth()->user()->isAdmin())
    <x-layouts.admin-shell title="Bulk Upload History">
        <livewire:bulk-upload-history />
    </x-layouts.admin-shell>
@else
    <x-layouts.user-shell title="Bulk Upload History">
        <livewire:bulk-upload-history />
    </x-layouts.user-shell>
@endif

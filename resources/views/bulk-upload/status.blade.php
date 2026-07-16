@php $isAdmin = auth()->user()->isAdmin(); @endphp

@if ($isAdmin)
    <x-layouts.admin-shell title="Batch Status">
        @include('bulk-upload.partials.status-content-admin')
    </x-layouts.admin-shell>
@else
    <x-layouts.user-shell title="Batch Status">
        @include('bulk-upload.partials.status-content-tenant')
    </x-layouts.user-shell>
@endif

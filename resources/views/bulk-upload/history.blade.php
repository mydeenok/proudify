@php $isAdmin = auth()->user()->isAdmin(); @endphp

@if ($isAdmin)
    <x-layouts.admin-shell title="Bulk Upload History">
        @include('bulk-upload.partials.history-content', ['isAdmin' => true])
    </x-layouts.admin-shell>
@else
    <x-layouts.user-shell title="Bulk Upload History">
        @include('bulk-upload.partials.history-content', ['isAdmin' => false])
    </x-layouts.user-shell>
@endif

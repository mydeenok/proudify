<x-layouts.admin-shell title="New Template">
    <div class="mb-xl">
        <h2 class="font-headline-xl text-headline-xl text-on-surface mb-xs">New Template</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">
            Author raw HTML for now — the visual builder lands in Milestone 4.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.templates.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.templates._form')
    </form>
</x-layouts.admin-shell>

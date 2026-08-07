<x-layouts.admin-shell title="Edit Template">
    <div class="mb-xl">
        <h2 class="font-headline-xl text-headline-xl text-on-surface mb-xs">Edit Template</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">
            {{ $template->name }} — layout & fields are edited in the Visual Builder.
            <a href="{{ route('admin.templates.builder', $template) }}" class="text-primary hover:underline ml-xs">Open Builder</a>
        </p>
    </div>

    <form method="POST" action="{{ route('admin.templates.update', $template) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('admin.templates._form')
    </form>
</x-layouts.admin-shell>

<x-layouts.admin-shell title="New Template">
    <div class="mb-xl">
        <h2 class="font-headline-xl text-headline-xl text-on-surface mb-xs">New Template</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">
            Enter the basics — you’ll design the certificate in the Visual Builder next.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.templates.store') }}" enctype="multipart/form-data">
        @csrf
        @include('admin.templates._form')
    </form>
</x-layouts.admin-shell>

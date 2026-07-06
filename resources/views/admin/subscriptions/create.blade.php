<x-layouts.admin-shell title="New Plan">
    <div class="mb-xl">
        <h2 class="font-headline-xl text-headline-xl text-on-surface">New Subscription Plan</h2>
    </div>

    <form method="POST" action="{{ route('admin.subscriptions.store') }}">
        @csrf
        @include('admin.subscriptions._form')
    </form>
</x-layouts.admin-shell>

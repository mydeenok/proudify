<x-layouts.admin-shell title="Edit Plan">
    <div class="mb-xl">
        <h2 class="font-headline-xl text-headline-xl text-on-surface">{{ $plan->name }}</h2>
    </div>

    <form method="POST" action="{{ route('admin.subscriptions.update', $plan) }}">
        @csrf
        @method('PUT')
        @include('admin.subscriptions._form')
    </form>
</x-layouts.admin-shell>

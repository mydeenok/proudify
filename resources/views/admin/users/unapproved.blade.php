<x-layouts.admin-shell title="Unapproved Users">
    <div class="mb-xl">
        <h2 class="font-headline-xl text-headline-xl text-on-surface mb-xs">Pending Approvals</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">
            These accounts verified their email and are waiting on a trust decision before they can log in.
        </p>
    </div>

    <div class="bg-surface rounded-xl border border-outline-variant overflow-hidden shadow-sm">
        @forelse ($users as $pendingUser)
            <div class="flex items-center justify-between px-lg py-md border-b border-outline-variant last:border-b-0">
                <div>
                    <p class="font-label-md text-label-md text-on-surface font-semibold">{{ $pendingUser->name }}</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $pendingUser->organization_name }} &middot; {{ $pendingUser->email }}</p>
                </div>
                <div class="flex items-center gap-sm">
                    <form method="POST" action="{{ route('admin.users.approve', $pendingUser) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="inline-flex items-center gap-xs h-9 px-md bg-primary-container text-on-primary rounded-lg font-label-sm text-label-sm font-semibold hover:bg-primary transition-colors">
                            <span class="material-symbols-outlined text-[16px]">check</span>
                            Approve
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.users.reject', $pendingUser) }}" onsubmit="return confirm('Reject this registration? The account will be deleted.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center gap-xs h-9 px-md bg-surface border border-outline-variant text-on-surface rounded-lg font-label-sm text-label-sm hover:bg-surface-container-highest transition-colors">
                            <span class="material-symbols-outlined text-[16px]">close</span>
                            Reject
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <p class="px-lg py-xl text-center font-body-md text-body-md text-on-surface-variant">No pending approvals.</p>
        @endforelse
    </div>

    <div class="mt-lg">
        {{ $users->links() }}
    </div>
</x-layouts.admin-shell>

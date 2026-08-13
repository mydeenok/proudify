<div class="card-surface shadow-card-sm overflow-hidden">
    <div class="flex flex-col md:flex-row justify-between items-center border-b border-outline-variant px-md">
        <div class="flex gap-lg w-full md:w-auto overflow-x-auto hide-scrollbar">
            <span class="py-sm font-label-md text-label-md text-primary border-b-2 border-primary whitespace-nowrap">
                Active Users
            </span>
            <a href="{{ route('admin.users.unapproved') }}"
                wire:navigate
                class="py-sm font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors whitespace-nowrap flex items-center gap-2">
                Pending Approvals
                @if ($pendingApprovalCount > 0)
                    <span class="bg-amber-500/10 text-amber-600 px-2 py-0.5 rounded-full text-[10px] font-bold">{{ $pendingApprovalCount }}</span>
                @endif
            </a>
        </div>

        <div class="flex flex-wrap items-center gap-sm py-sm w-full md:w-auto">
            <div class="relative w-full md:w-64">
                <x-search-input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search users..."
                    class="h-10 text-body-sm"
                />
            </div>

            <select
                wire:model.live="status"
                class="h-10 rounded-lg border border-outline-variant bg-surface px-sm font-body-sm text-body-sm text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/10 outline-none"
            >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="pending_otp">Pending OTP</option>
                <option value="pending_approval">Pending Approval</option>
                <option value="suspended">Suspended</option>
                {{-- No "rejected" option: UserController::reject() deletes
                     the user row rather than ever setting this status, so
                     filtering by it always returns zero rows. --}}
            </select>

            <select
                wire:model.live="role"
                class="h-10 rounded-lg border border-outline-variant bg-surface px-sm font-body-sm text-body-sm text-on-surface focus:border-primary focus:ring-2 focus:ring-primary/10 outline-none"
            >
                <option value="">All roles</option>
                <option value="admin">Admin</option>
                <option value="user">User</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="data-table w-full text-left border-collapse min-w-[800px]">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr class="group h-14" wire:key="user-{{ $user->id }}">
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-surface-container-high text-primary flex items-center justify-center font-bold font-label-sm shrink-0 border border-outline-variant">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-label-md text-label-md text-on-surface font-semibold">{{ $user->name }}</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $user->organization_name }} &middot; {{ $user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="capitalize">{{ $user->role }}</td>
                        <td>
                            <x-status-badge :status="$user->status">
                                {{ ucfirst(str_replace('_', ' ', $user->status)) }}
                            </x-status-badge>
                        </td>
                        <td>{{ $user->created_at->format('d M Y') }}</td>
                        <td class="text-right">
                            <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                @if (! $user->isAdmin())
                                    @if ($user->status === 'pending_approval')
                                        <form method="POST" action="{{ route('admin.users.approve', $user) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-primary hover:text-primary/80 transition-colors p-1 font-label-sm text-label-sm" title="Approve">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.reject', $user) }}" class="inline" onsubmit="return confirm('Reject this registration? The account will be deleted.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-error hover:text-error/80 transition-colors p-1 font-label-sm text-label-sm" title="Reject">Reject</button>
                                        </form>
                                    @elseif ($user->status === 'suspended')
                                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-on-surface-variant hover:text-primary transition-colors p-1 font-label-sm text-label-sm" title="Reactivate">Reactivate</button>
                                        </form>
                                    @elseif ($user->status === 'active')
                                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="inline" onsubmit="return confirm('Suspend this user? They will be logged out and blocked from logging in.');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-error hover:text-error/80 transition-colors p-1 font-label-sm text-label-sm" title="Suspend">Suspend</button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-md py-xl text-center font-body-md text-body-md text-on-surface-variant">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="border-t border-outline-variant p-md flex items-center justify-between">
        <span class="font-body-sm text-body-sm text-on-surface-variant">
            {{ $users->total() }} total users
        </span>
        <div>
            {{ $users->links() }}
        </div>
    </div>
</div>

{{-- 30s (was 10s) - the unread badge doesn't need near-real-time
     updates, and this poll runs continuously for every logged-in session
     on every page that includes the bell, whether or not the dropdown is
     open. Kept unconditional (not gated on `open`) since the badge count
     itself should still refresh in the background. --}}
<div class="relative" x-data="{ open: false }" wire:poll.30s>
    <button
        type="button"
        @click="open = !open"
        @click.outside="open = false"
        class="relative p-xs text-on-surface-variant hover:text-primary transition-colors rounded-full hover:bg-surface-variant"
        aria-label="Notifications"
    >
        <span class="material-symbols-outlined">notifications</span>
        @if ($unreadCount > 0)
            <span class="absolute top-0 right-0 min-w-[16px] h-4 px-1 rounded-full bg-error text-on-error text-[10px] font-bold flex items-center justify-center">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    <div x-show="open" x-cloak class="absolute right-0 mt-sm w-80 bg-surface-container-lowest border border-outline-variant rounded-lg shadow-lg z-50 overflow-hidden">
        <div class="flex items-center justify-between px-md py-sm border-b border-outline-variant">
            <span class="font-label-md text-label-md text-on-surface">Notifications</span>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="font-label-sm text-label-sm text-primary hover:underline">Mark all read</button>
            @endif
        </div>

        <div class="max-h-96 overflow-y-auto divide-y divide-outline-variant">
            @forelse ($recentNotifications as $notification)
                <a
                    wire:key="notification-{{ $notification->id }}"
                    href="{{ route('notifications.open', $notification->id) }}"
                    wire:navigate
                    class="block px-md py-sm hover:bg-surface-container-low transition-colors {{ $notification->read_at ? '' : 'bg-primary-container/10' }}"
                >
                    <div class="flex items-start gap-xs">
                        @unless ($notification->read_at)
                            <span class="w-1.5 h-1.5 rounded-full bg-primary mt-1.5 shrink-0"></span>
                        @endunless
                        <div class="min-w-0">
                            <p class="font-label-sm text-label-sm text-on-surface truncate">{{ $notification->data['title'] ?? 'Notification' }}</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ $notification->data['body'] ?? '' }}</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </a>
            @empty
                <p class="px-md py-lg text-center font-body-sm text-body-sm text-on-surface-variant">No notifications yet.</p>
            @endforelse
        </div>
    </div>
</div>

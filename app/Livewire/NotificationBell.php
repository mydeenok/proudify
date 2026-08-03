<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function markAllRead(): void
    {
        abort_unless(auth()->check(), 403);

        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        return view('livewire.notification-bell', [
            'recentNotifications' => auth()->user()->notifications()->latest()->limit(8)->get(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}

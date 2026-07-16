<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Marks one notification read and redirects to whatever it links to -
     * the single click a user makes from the bell dropdown does both at
     * once, rather than requiring a separate "mark read" action first.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);

        if (! $record->read_at) {
            $record->markAsRead();
        }

        // Built here, in a real request, rather than stored pre-baked at
        // creation time - notifications are frequently created inside a
        // queued job, where route() has no request to resolve the host
        // from and would fall back to APP_URL instead.
        $url = isset($record->data['route'])
            ? route($record->data['route'], $record->data['route_params'] ?? [])
            : route('dashboard');

        return redirect($url);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}

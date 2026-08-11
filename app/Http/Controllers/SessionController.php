<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    /**
     * Revoking a single other device deletes its row directly - scoped to
     * the authenticated user's own sessions, and never the current one
     * (that's what "log out" already does), so this can't be used to
     * delete someone else's session or lock yourself out by accident.
     */
    public function destroy(Request $request, string $sessionId): RedirectResponse
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->with('status', 'That is your current session - use Log Out for this device instead.');
        }

        DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('status', 'session-revoked');
    }

    /**
     * Password-confirmed bulk revoke via Laravel's built-in
     * logoutOtherDevices() - it re-hashes the current session's auth
     * cookie and invalidates every other session row for this user,
     * rather than this controller trying to replicate that itself.
     */
    public function destroyOthers(Request $request): RedirectResponse
    {
        try {
            $request->validate(['password' => ['required', 'current_password']]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors(), 'logoutOtherDevices');
        }

        Auth::logoutOtherDevices($request->password);

        return back()->with('status', 'other-sessions-revoked');
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth check for the 3-gate onboarding flow. Login itself
 * already refuses non-active accounts (see AuthenticatedSessionController),
 * but this guards any request made with a session that outlived a status
 * change (e.g. an admin suspends a user mid-session).
 */
class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account is not active. Please contact support if you believe this is a mistake.']);
        }

        return $next($request);
    }
}

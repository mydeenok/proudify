<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the user-facing login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate a tenant user. Deliberately rejects admin accounts here
     * (and the mirror check in adminStore() rejects user accounts) so the
     * two login surfaces never get confused, per the reference app's
     * pattern of a dedicated /adm/login URL.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        if ($user->isAdmin()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Please use the admin login page.',
            ]);
        }

        $this->rejectUnlessActive($request, $user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Display the admin login view.
     */
    public function adminCreate(): View
    {
        return view('auth.admin-login');
    }

    /**
     * Authenticate a Proudify staff account.
     */
    public function adminStore(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        if (! $user->isAdmin()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Please use the standard login page.',
            ]);
        }

        $this->rejectUnlessActive($request, $user);

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Enforce the 3-gate onboarding state at login time, since this is the
     * one place we can give the user a specific, actionable message.
     */
    private function rejectUnlessActive(LoginRequest $request, $user): void
    {
        if ($user->isActive()) {
            return;
        }

        Auth::logout();

        $message = match ($user->status) {
            'pending_otp' => 'Please verify your email before logging in.',
            'pending_approval' => 'Your account is awaiting admin approval.',
            'rejected' => 'Your registration request was not approved.',
            'suspended' => 'Your account has been suspended. Please contact support.',
            default => 'Your account is not active.',
        };

        throw ValidationException::withMessages(['email' => $message]);
    }
}

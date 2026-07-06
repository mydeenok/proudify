<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SsoController extends Controller
{
    public function __construct(private readonly SsoService $sso) {}

    public function redirectToProvider(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['Google', 'Facebook'], true), 404);

        return redirect($this->sso->buildAuthUrl($provider, route('sso.callback')));
    }

    /**
     * Gate 1+2 are implicitly satisfied by the identity provider having
     * already verified the email, so an SSO sign-in lands a new user
     * straight into Gate 3 (admin approval) instead of the OTP flow.
     *
     * Crucially: always look up by email BEFORE creating a user. This is
     * the exact discipline the reference app's SSO path was missing in
     * places — skipping it lets the same person end up with two
     * disconnected accounts and two certificate histories.
     */
    public function handleProviderCallback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        abort_if(! $code, 400);

        $tokens = $this->sso->exchangeCodeForToken($code, route('sso.callback'));
        $claims = $this->sso->decodeIdToken($tokens['id_token']);

        $email = $claims['email'] ?? null;

        abort_if(! $email, 422, 'Identity provider did not return an email address.');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'first_name' => $claims['given_name'] ?? Str::before($claims['name'] ?? 'New', ' '),
                'last_name' => $claims['family_name'] ?? Str::after($claims['name'] ?? 'User', ' '),
                'organization_name' => null,
                'email' => $email,
                'phone' => null,
                'password' => Str::random(40),
                'role' => 'user',
                'status' => 'pending_approval',
            ]);
        }

        if ($user->isAdmin()) {
            throw ValidationException::withMessages(['email' => 'This account cannot sign in via SSO.']);
        }

        if (! $user->isActive()) {
            return redirect()->route('otp.pending-approval');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($this->sso->getLogoutUrl());
    }
}

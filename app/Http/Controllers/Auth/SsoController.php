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

    public function redirectToProvider(string $provider, Request $request): RedirectResponse
    {
        abort_unless(in_array($provider, ['Google', 'Facebook'], true), 404);

        $state = Str::random(40);
        $request->session()->put('sso_state', $state);

        return redirect($this->sso->buildAuthUrl($provider, route('sso.callback'), $state));
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
        $state = $request->query('state');
        $expectedState = $request->session()->pull('sso_state');

        abort_if(! $code, 400);

        // Without this check, an attacker can start their own OAuth flow,
        // get a valid code for their own identity, then trick a victim into
        // visiting this callback with that code — the victim's browser
        // would end up authenticated as the attacker's account (login
        // CSRF). The state value is single-use (pull() above already
        // removed it from the session) and tied to the session that
        // initiated the redirect, so a code/state pair from anywhere else
        // can never match.
        abort_if(! $state || ! $expectedState || ! hash_equals($expectedState, $state), 400, 'Invalid or expired SSO request.');

        $tokens = $this->sso->exchangeCodeForToken($code, route('sso.callback'));
        $claims = $this->sso->decodeIdToken($tokens['id_token']);

        $email = $claims['email'] ?? null;

        abort_if(! $email, 422, 'Identity provider did not return an email address.');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = new User([
                'first_name' => $claims['given_name'] ?? Str::before($claims['name'] ?? 'New', ' '),
                'last_name' => $claims['family_name'] ?? Str::after($claims['name'] ?? 'User', ' '),
                'organization_name' => null,
                'email' => $email,
                'phone' => null,
                'password' => Str::random(40),
            ]);
            $user->forceFill(['role' => 'user', 'status' => 'pending_approval'])->save();
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

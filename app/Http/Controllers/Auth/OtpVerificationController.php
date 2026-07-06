<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OtpVerificationController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Display the OTP entry form for the account currently mid-registration.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        return view('auth.verify-otp', ['email' => $user->email]);
    }

    /**
     * Gate 2: confirm the OTP proves ownership of the registered email,
     * then move the account into the admin-approval queue (Gate 3).
     */
    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        $request->validate([
            'otp_code' => ['required', 'string', 'size:6'],
        ]);

        if (! $this->otpService->verify($user, $request->string('otp_code'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'otp_code' => 'That code is invalid or has expired.',
            ]);
        }

        $user->forceFill(['status' => 'pending_approval'])->save();
        $this->otpService->clear($user);

        $request->session()->forget('otp_user_id');

        return redirect()->route('otp.pending-approval');
    }

    /**
     * Re-issue a fresh OTP. Rate-limited at the route level (throttle
     * middleware) so this can't be used to email-bomb an address.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('register');
        }

        $this->otpService->issueFor($user);

        return back()->with('status', 'A new code has been sent to your email.');
    }

    public function pendingApproval(): View
    {
        return view('auth.pending-approval');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('otp_user_id');

        if (! $userId) {
            return null;
        }

        return User::whereKey($userId)->where('status', 'pending_otp')->first();
    }
}

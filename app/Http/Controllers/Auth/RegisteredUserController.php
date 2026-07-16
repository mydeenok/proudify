<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpDeliveryFailedException;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly OtpService $otpService) {}

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Gate 1: register the account and issue an OTP. The account cannot
     * log in yet — it still needs OTP verification (Gate 2) and admin
     * approval (Gate 3).
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'organization_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = new User($validated);
        $user->forceFill(['role' => 'user', 'status' => 'pending_otp'])->save();

        session(['otp_user_id' => $user->id]);

        try {
            $code = $this->otpService->issueFor($user);
        } catch (OtpDeliveryFailedException $exception) {
            Log::error('Failed to send registration OTP email.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            $this->stashDebugCode($exception->otpCode);

            return redirect()->route('otp.verify')
                ->with('status', 'We hit a snag sending your verification email. Tap "Resend it" below to try again.');
        }

        $this->stashDebugCode($code);

        return redirect()->route('otp.verify');
    }

    /**
     * Local-only: lets the verify-otp page show the real code on-screen
     * since there's no working mail delivery to check in dev. Never runs
     * outside app()->environment('local'), so it can't leak in production.
     */
    private function stashDebugCode(string $code): void
    {
        if (app()->environment('local')) {
            session(['otp_debug_code' => $code]);
        }
    }
}

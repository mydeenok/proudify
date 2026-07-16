<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OtpCodeNotification;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Gate 1 -> Gate 2 of onboarding: issuing and checking the OTP that proves
 * email ownership. Shared by self-registration, admin-created users, and
 * the resend action so the generation/validation rules live in one place.
 */
class OtpService
{
    private const CODE_LENGTH = 6;

    private const EXPIRY_MINUTES = 10;

    /**
     * Returns the plain code (not the hash stored on the user) purely so
     * local/dev callers can surface it on-screen when mail delivery isn't
     * set up - never used for anything security-relevant. On a delivery
     * failure the code still needs to reach the caller for that same
     * reason, so it's thrown alongside the underlying exception rather
     * than lost.
     *
     * @throws OtpDeliveryFailedException
     */
    public function issueFor(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_code' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ])->save();

        try {
            $user->notify(new OtpCodeNotification($code));
        } catch (Throwable $exception) {
            throw new OtpDeliveryFailedException($code, $exception);
        }

        return $code;
    }

    public function verify(User $user, string $code): bool
    {
        if (! $user->otp_code || ! $user->otp_expires_at) {
            return false;
        }

        if ($user->otp_expires_at->isPast()) {
            return false;
        }

        return Hash::check($code, $user->otp_code);
    }

    public function clear(User $user): void
    {
        $user->forceFill([
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();
    }
}

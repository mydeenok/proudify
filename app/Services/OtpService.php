<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OtpCodeNotification;
use Illuminate\Support\Facades\Hash;

/**
 * Gate 1 -> Gate 2 of onboarding: issuing and checking the OTP that proves
 * email ownership. Shared by self-registration, admin-created users, and
 * the resend action so the generation/validation rules live in one place.
 */
class OtpService
{
    private const CODE_LENGTH = 6;

    private const EXPIRY_MINUTES = 10;

    public function issueFor(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'otp_code' => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ])->save();

        $user->notify(new OtpCodeNotification($code));
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

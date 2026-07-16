<?php

namespace App\Services;

use RuntimeException;
use Throwable;

/**
 * Carries the plain OTP code alongside the underlying mail failure, so a
 * caller can still show it on-screen for local dev (where mail delivery
 * isn't set up) without having to re-derive it from the hash on the user.
 */
class OtpDeliveryFailedException extends RuntimeException
{
    public function __construct(public readonly string $otpCode, Throwable $previous)
    {
        parent::__construct($previous->getMessage(), previous: $previous);
    }
}

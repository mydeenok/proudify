<?php

namespace App\Exceptions;

use RuntimeException;

class SubscriptionQuotaExceededException extends RuntimeException
{
    public static function certificates(): self
    {
        return new self('Your subscription\'s certificate limit has been reached.');
    }

    public static function users(): self
    {
        return new self('Your subscription\'s recipient limit has been reached.');
    }

    public static function noActiveSubscription(): self
    {
        return new self('No active subscription. Please choose a plan to continue issuing certificates.');
    }
}

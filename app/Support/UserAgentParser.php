<?php

namespace App\Support;

/**
 * Deliberately not a full user-agent parsing library - the active-sessions
 * screen only needs a friendly "Browser on OS" label, not exhaustive
 * device detection, so a small set of substring checks covers the common
 * cases without a new dependency.
 */
class UserAgentParser
{
    public static function label(?string $userAgent): string
    {
        if (blank($userAgent)) {
            return 'Unknown device';
        }

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'a browser',
        };

        $os = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $os ? "{$browser} on {$os}" : $browser;
    }
}

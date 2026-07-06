<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Determines INR vs USD pricing by IP. Local/private IPs (dev environment)
 * default to India. Public IPs get a best-effort lookup against ip-api.com's
 * free tier — no API key required, but it's a third party with no uptime
 * guarantee, so any failure or timeout falls back to USD rather than
 * blocking checkout.
 */
class GeoLocationService
{
    public function isIndianIp(string $ip): bool
    {
        if ($this->isPrivateOrLocal($ip)) {
            return true;
        }

        return Cache::remember("geolocation:{$ip}", now()->addDay(), function () use ($ip) {
            try {
                $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}", ['fields' => 'countryCode']);

                return $response->successful() && $response->json('countryCode') === 'IN';
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function currencyFor(string $ip): string
    {
        return $this->isIndianIp($ip) ? 'INR' : 'USD';
    }

    private function isPrivateOrLocal(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}

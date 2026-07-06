<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around AWS Cognito's hosted-UI OAuth2 code-grant flow.
 * Ported from the reference app's CognitoService pattern: plain HTTP calls,
 * no AWS SDK dependency needed since the flow is just two REST calls.
 */
class SsoService
{
    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $domain;

    public function __construct()
    {
        $this->clientId = (string) config('services.cognito.client_id');
        $this->clientSecret = (string) config('services.cognito.client_secret');
        $this->domain = rtrim((string) config('services.cognito.domain'), '/');
    }

    public function buildAuthUrl(string $provider, string $redirectUri): string
    {
        return $this->domain.'/oauth2/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'identity_provider' => $provider,
            'scope' => 'openid email profile',
        ]);
    }

    /**
     * @return array{id_token: string, access_token: string}
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = Http::asForm()->post("{$this->domain}/oauth2/token", [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        return $response->throw()->json();
    }

    /**
     * Decode the ID token payload WITHOUT re-verifying its signature. This
     * is safe here specifically because the token arrived via a
     * server-to-server exchange (exchangeCodeForToken above) and never
     * passed through the browser/client.
     *
     * @return array{email: string, given_name: ?string, family_name: ?string, name: ?string}
     */
    public function decodeIdToken(string $idToken): array
    {
        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            throw new \RuntimeException('Malformed ID token.');
        }

        $payload = json_decode(base64_decode(strtr($segments[1], '-_', '+/')), associative: true) ?? [];

        if (($payload['exp'] ?? 0) < time()) {
            throw new \RuntimeException('ID token has expired.');
        }

        return $payload;
    }

    public function getLogoutUrl(): string
    {
        return $this->domain.'/logout?'.http_build_query([
            'client_id' => $this->clientId,
            'logout_uri' => url('/'),
        ]);
    }
}

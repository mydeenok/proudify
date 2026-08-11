<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every authenticated API call against the token that made it, for
 * admin visibility only - see the analysis behind this feature. Origin is
 * stored as-received and shown as "self-reported, not verified" in the
 * admin UI, since a non-browser caller can send any value here (or none).
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $token = $request->user()?->currentAccessToken();

        if ($token) {
            ApiRequestLog::create([
                'personal_access_token_id' => $token->id,
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'ip_address' => $request->ip(),
                'origin' => $request->header('Origin') ?? $request->header('Referer'),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}

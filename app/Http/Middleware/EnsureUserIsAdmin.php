<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single, canonical admin gate. Only this middleware should ever be
 * used to protect /admin routes — do not introduce a second admin-check
 * middleware class, that duplication was a source of confusion in the
 * reference app this project supersedes.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check() || ! Auth::user()->isAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant-exclusive routes (dashboard, templates, certificates, subscriptions,
 * purchase) have no role check of their own - login is the only place that
 * decides which dashboard an account lands on. Without this, an admin
 * session that later navigates into one of these URLs (bookmark, typed
 * URL, stale link) gets served that admin account's own certificate/
 * subscription data as if they were a customer, which is both confusing
 * and not what any of those pages are for.
 *
 * Deliberately not applied to bulk-upload.* or profile.* - admin issuance
 * reuses the tenant bulk-upload routes on purpose (see BulkUploadController
 * doc comment), and there's no admin-side profile page to send them to
 * instead.
 */
class RedirectAdminFromTenantRoutes
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}

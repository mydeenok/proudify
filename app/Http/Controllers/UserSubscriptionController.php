<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Subscriptions are retired - billing moved to pay-per-certificate (see
 * CertificateOrder). The view/route stay in place (not deleted) for a
 * tenant with real historical subscription data to fall back to later if
 * needed; for now this just redirects instead of showing a page about a
 * billing model that no longer applies going forward.
 */
class UserSubscriptionController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard')->with('status', 'Subscriptions have been replaced by pay-per-certificate billing.');
    }
}

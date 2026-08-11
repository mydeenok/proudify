<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Subscription-tier pricing is retired - certificates are pay-per-unit now
 * (see CertificatePricingService / BillingSetting), priced at checkout
 * rather than on a dedicated plans page. The view/route stay in place
 * (not deleted) in case a real pricing page is wanted here later; for now
 * this just sends visitors somewhere useful instead of showing stale
 * subscription tiers.
 */
class PricingController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $destination = $request->user() ? route('dashboard') : url('/');

        return redirect($destination)->with('status', 'Certificates are billed per unit now - pricing shows up at checkout when you create or issue one.');
    }
}

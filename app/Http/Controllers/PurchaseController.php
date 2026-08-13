<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Subscriptions are retired - billing moved to pay-per-certificate (see
 * CertificateOrder / CertificatePricingService), same as PricingController
 * and UserSubscriptionController. This class used to take live Razorpay
 * payments for plans that SubscriptionQuotaService (the thing meant to
 * enforce them) never actually checks anywhere in the certificate/bulk
 * issuance paths - so leaving it live let a tenant genuinely pay for a
 * plan that granted nothing. Views/routes stay in place (not deleted) in
 * case real subscription tiers come back later.
 */
class PurchaseController extends Controller
{
    public function show(Request $request, Subscription $subscription): RedirectResponse
    {
        return redirect()->route('dashboard')
            ->with('status', 'Subscriptions have been replaced by pay-per-certificate billing.');
    }

    public function process(Request $request, Subscription $subscription): RedirectResponse
    {
        return redirect()->route('dashboard')
            ->with('status', 'Subscriptions have been replaced by pay-per-certificate billing.');
    }

    public function createOrder(Request $request, Subscription $subscription): JsonResponse
    {
        return response()->json([
            'message' => 'Subscriptions have been replaced by pay-per-certificate billing.',
        ], 410);
    }

    public function verifyPayment(Request $request, Subscription $subscription): JsonResponse
    {
        return response()->json([
            'message' => 'Subscriptions have been replaced by pay-per-certificate billing.',
        ], 410);
    }
}

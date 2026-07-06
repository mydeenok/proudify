<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\UserSubscription;
use App\Services\GeoLocationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function index(Request $request, GeoLocationService $geo): View
    {
        $plans = Subscription::active()
            ->orderByDesc('is_default_free_plan')
            ->orderBy('sort_order')
            ->get();

        $currency = $geo->currencyFor($request->ip());

        $activeSubscription = $request->user()
            ? UserSubscription::where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->where('payment_status', 'completed')
                ->where('end_date', '>', now())
                ->latest('start_date')
                ->first()
            : null;

        return view('pricing', compact('plans', 'currency', 'activeSubscription'));
    }
}

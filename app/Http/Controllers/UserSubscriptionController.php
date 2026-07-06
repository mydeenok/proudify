<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class UserSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $subscriptions = $request->user()
            ->userSubscriptions()
            ->with('subscription')
            ->latest('start_date')
            ->paginate(10);

        $active = $request->user()
            ->userSubscriptions()
            ->where('is_active', true)
            ->where('payment_status', 'completed')
            ->where('end_date', '>', now())
            ->latest('start_date')
            ->first();

        return view('user-subscriptions.index', compact('subscriptions', 'active'));
    }
}

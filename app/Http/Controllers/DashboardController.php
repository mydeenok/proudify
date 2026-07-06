<?php

namespace App\Http\Controllers;

use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $recentCertificates = $user->certificates()->with('template')->latest()->limit(4)->get();
        $certificateCount = $user->certificates()->count();

        $activeSubscription = UserSubscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('payment_status', 'completed')
            ->where('end_date', '>', now())
            ->with('subscription')
            ->latest('start_date')
            ->first();

        return view('dashboard', compact('user', 'recentCertificates', 'certificateCount', 'activeSubscription'));
    }
}

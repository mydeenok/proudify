<?php

namespace App\Http\Controllers;

use App\Models\CertificateOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $monthStart = now()->startOfMonth();

        $recentCertificates = $user->certificates()->with('template')->latest()->limit(4)->get();
        $certificateCount = $user->certificates()->count();

        $certificatesThisMonth = $user->certificates()->where('created_at', '>=', $monthStart)->count();
        $spentThisMonth = (float) CertificateOrder::where('user_id', $user->id)
            ->where('status', 'paid')
            ->where('paid_at', '>=', $monthStart)
            ->sum('total_amount');

        $lastOrder = CertificateOrder::where('user_id', $user->id)
            ->where('status', 'paid')
            ->latest('paid_at')
            ->first();

        return view('dashboard', compact(
            'user',
            'recentCertificates',
            'certificateCount',
            'certificatesThisMonth',
            'spentThisMonth',
            'lastOrder',
        ));
    }
}

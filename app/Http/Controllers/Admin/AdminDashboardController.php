<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\EmailLog;
use App\Models\Template;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $topTemplates = Template::query()
            ->withCount('certificates')
            ->orderByDesc('certificates_count')
            ->limit(4)
            ->get();

        $maxTemplateUsage = max($topTemplates->max('certificates_count') ?? 1, 1);

        $emailsSentToday = EmailLog::sent()->where('created_at', '>=', now()->startOfDay())->count();
        $emailsFailedToday = EmailLog::failed()->where('created_at', '>=', now()->startOfDay())->count();

        return view('admin.dashboard', [
            'pendingApprovalCount' => User::where('status', 'pending_approval')->count(),
            'activeUserCount' => User::where('status', 'active')->count(),
            'certificatesIssuedThisMonth' => Certificate::where('created_at', '>=', now()->startOfMonth())->count(),
            'totalCertificateCount' => Certificate::count(),
            'activeSubscriberCount' => UserSubscription::where('is_active', true)->where('payment_status', 'completed')->count(),
            'emailsSentToday' => $emailsSentToday,
            'emailsFailedToday' => $emailsFailedToday,
            'pendingUsers' => User::where('status', 'pending_approval')->latest()->limit(5)->get(),
            'topTemplates' => $topTemplates,
            'maxTemplateUsage' => $maxTemplateUsage,
            'recentCertificates' => Certificate::query()
                ->with(['template', 'user'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateVerification;
use App\Models\Template;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminAnalyticsController extends Controller
{
    public function index(): View
    {
        $revenueByCurrency = UserSubscription::where('payment_status', 'completed')
            ->select('currency', DB::raw('SUM(amount_paid) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        $usersByPlan = UserSubscription::query()
            ->where('user_subscriptions.is_active', true)
            ->where('user_subscriptions.payment_status', 'completed')
            ->join('subscriptions', 'subscriptions.id', '=', 'user_subscriptions.subscription_id')
            ->select('subscriptions.name', DB::raw('COUNT(*) as total'))
            ->groupBy('subscriptions.name')
            ->orderByDesc('total')
            ->pluck('total', 'subscriptions.name');

        $issuanceByDay = Certificate::where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $issuanceSeries = collect(range(29, 0))->mapWithKeys(function ($daysAgo) use ($issuanceByDay) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [$date => (int) ($issuanceByDay[$date] ?? 0)];
        });

        $revenueSeries = collect(range(5, 0))->mapWithKeys(function ($monthsAgo) {
            $start = now()->subMonths($monthsAgo)->startOfMonth();
            $end = now()->subMonths($monthsAgo)->endOfMonth();
            $label = $start->format('M');
            $total = UserSubscription::where('payment_status', 'completed')
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_paid');

            return [$label => (float) $total];
        });

        $verificationCounts = CertificateVerification::select('result', DB::raw('COUNT(*) as total'))
            ->groupBy('result')
            ->pluck('total', 'result');

        $totalVerifications = $verificationCounts->sum();
        $verificationRate = $totalVerifications > 0
            ? round((($verificationCounts['valid'] ?? 0) / $totalVerifications) * 100, 1)
            : null;

        $topTemplates = Template::query()
            ->withCount('certificates')
            ->orderByDesc('certificates_count')
            ->limit(5)
            ->get();

        $planColors = ['#d92727', '#151c27', '#dce2f3', '#cea62c', '#5e5e62'];
        $totalPlanUsers = $usersByPlan->sum();
        $donutSegments = [];
        $offset = 0;
        $colorIndex = 0;
        foreach ($usersByPlan as $planName => $count) {
            if ($totalPlanUsers === 0) {
                break;
            }
            $pct = ($count / $totalPlanUsers) * 100;
            $color = $planColors[$colorIndex % count($planColors)];
            $donutSegments[] = [
                'name' => $planName,
                'count' => $count,
                'percent' => round($pct),
                'color' => $color,
                'from' => $offset,
                'to' => $offset + $pct,
            ];
            $offset += $pct;
            $colorIndex++;
        }

        $donutGradient = $totalPlanUsers > 0
            ? 'conic-gradient('.collect($donutSegments)->map(fn ($s) => "{$s['color']} {$s['from']}% {$s['to']}%")->implode(', ').')'
            : 'conic-gradient(#dce2f3 0% 100%)';

        $maxRevenue = max($revenueSeries->max() ?: 1, 1);
        $revenuePoints = $revenueSeries->values();
        $pointCount = max($revenuePoints->count() - 1, 1);

        return view('admin.analytics.index', [
            'revenueByCurrency' => $revenueByCurrency,
            'usersByPlan' => $usersByPlan,
            'donutSegments' => $donutSegments,
            'donutGradient' => $donutGradient,
            'totalPlanUsers' => $totalPlanUsers,
            'issuanceSeries' => $issuanceSeries,
            'revenueSeries' => $revenueSeries,
            'maxRevenue' => $maxRevenue,
            'revenuePoints' => $revenuePoints,
            'pointCount' => $pointCount,
            'verificationCounts' => $verificationCounts,
            'totalVerifications' => $totalVerifications,
            'verificationRate' => $verificationRate,
            'totalCertificatesIssued' => Certificate::count(),
            'topTemplates' => $topTemplates,
        ]);
    }
}

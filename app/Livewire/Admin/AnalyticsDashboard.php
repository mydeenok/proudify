<?php

namespace App\Livewire\Admin;

use App\Models\Certificate;
use App\Models\CertificateVerification;
use App\Models\EmailLog;
use App\Models\Template;
use App\Models\UserSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class AnalyticsDashboard extends Component
{
    /** @var list<int> */
    public const PERIODS = [7, 30, 90];

    #[Url(history: true)]
    public int $period = 30;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        if (! in_array($this->period, self::PERIODS, true)) {
            $this->period = 30;
        }
    }

    public function updatedPeriod(mixed $value): void
    {
        $period = (int) $value;
        $this->period = in_array($period, self::PERIODS, true) ? $period : 30;
    }

    public function render(): View
    {
        $days = $this->period - 1;
        $periodStart = now()->subDays($days)->startOfDay();

        $revenueByCurrency = UserSubscription::where('payment_status', 'completed')
            ->where('created_at', '>=', $periodStart)
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

        $issuanceByDay = Certificate::where('created_at', '>=', $periodStart)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->pluck('total', 'day');

        $issuanceSeries = collect(range($days, 0))->mapWithKeys(function ($daysAgo) use ($issuanceByDay) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [$date => (int) ($issuanceByDay[$date] ?? 0)];
        });

        $revenueMonths = $this->period <= 30 ? 5 : 11;
        $revenueSeries = collect(range($revenueMonths, 0))->mapWithKeys(function ($monthsAgo) {
            $start = now()->subMonths($monthsAgo)->startOfMonth();
            $end = now()->subMonths($monthsAgo)->endOfMonth();
            $label = $start->format('M');
            $total = UserSubscription::where('payment_status', 'completed')
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_paid');

            return [$label => (float) $total];
        });

        $verificationCounts = CertificateVerification::where('created_at', '>=', $periodStart)
            ->select('result', DB::raw('COUNT(*) as total'))
            ->groupBy('result')
            ->pluck('total', 'result');

        $totalVerifications = $verificationCounts->sum();
        $verificationRate = $totalVerifications > 0
            ? round((($verificationCounts['valid'] ?? 0) / $totalVerifications) * 100, 1)
            : null;

        $topTemplates = Template::query()
            ->withCount(['certificates' => fn ($query) => $query->where('created_at', '>=', $periodStart)])
            ->orderByDesc('certificates_count')
            ->limit(5)
            ->get();

        $emailByDay = EmailLog::where('created_at', '>=', $periodStart)
            ->select(DB::raw('DATE(created_at) as day'), 'status', DB::raw('COUNT(*) as total'))
            ->groupBy('day', 'status')
            ->get()
            ->groupBy('day');

        $emailSeries = collect(range($days, 0))->map(function ($daysAgo) use ($emailByDay) {
            $date = now()->subDays($daysAgo)->toDateString();
            $rows = $emailByDay->get($date, collect());

            return [
                'day' => $date,
                'sent' => (int) ($rows->firstWhere('status', 'sent')->total ?? 0),
                'failed' => (int) ($rows->firstWhere('status', 'failed')->total ?? 0),
            ];
        });

        $totalEmailsSent = EmailLog::sent()->where('created_at', '>=', $periodStart)->count();
        $totalEmailsFailed = EmailLog::failed()->where('created_at', '>=', $periodStart)->count();
        $totalEmailsAttempted = $totalEmailsSent + $totalEmailsFailed;
        $emailDeliveryRate = $totalEmailsAttempted > 0
            ? round(($totalEmailsSent / $totalEmailsAttempted) * 100, 1)
            : null;

        $emailFailuresByType = EmailLog::failed()
            ->where('created_at', '>=', $periodStart)
            ->select('notification_class', DB::raw('COUNT(*) as total'))
            ->groupBy('notification_class')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        [$donutSegments, $donutGradient, $totalPlanUsers] = $this->donut($usersByPlan);

        $maxRevenue = max($revenueSeries->max() ?: 1, 1);
        $revenuePoints = $revenueSeries->values();
        $pointCount = max($revenuePoints->count() - 1, 1);

        return view('livewire.admin.analytics-dashboard', [
            'period' => $this->period,
            'periodLabel' => "Last {$this->period} Days",
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
            'totalCertificatesIssued' => Certificate::where('created_at', '>=', $periodStart)->count(),
            'topTemplates' => $topTemplates,
            'emailSeries' => $emailSeries,
            'totalEmailsSent' => $totalEmailsSent,
            'totalEmailsFailed' => $totalEmailsFailed,
            'emailDeliveryRate' => $emailDeliveryRate,
            'emailFailuresByType' => $emailFailuresByType,
        ]);
    }

    /**
     * @param  Collection<string, int|string>  $usersByPlan
     * @return array{0: list<array<string, mixed>>, 1: string, 2: int}
     */
    private function donut(Collection $usersByPlan): array
    {
        $planColors = ['#d92727', '#151c27', '#dce2f3', '#cea62c', '#5e5e62'];
        $totalPlanUsers = (int) $usersByPlan->sum();
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

        return [$donutSegments, $donutGradient, $totalPlanUsers];
    }
}

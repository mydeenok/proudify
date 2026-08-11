<?php

namespace App\Livewire\Admin;

use App\Models\Certificate;
use App\Models\CertificateOrder;
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

        $subscriptionRevenueByCurrency = UserSubscription::where('payment_status', 'completed')
            ->where('created_at', '>=', $periodStart)
            ->select('currency', DB::raw('SUM(amount_paid) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        // Pay-per-certificate revenue - filtered by paid_at (when the
        // payment actually cleared), not created_at, since a
        // CertificateOrder row exists from the moment checkout starts,
        // well before (or instead of) payment succeeding.
        $certificateOrderRevenue = (float) CertificateOrder::where('status', 'paid')
            ->where('paid_at', '>=', $periodStart)
            ->sum('total_amount');

        $revenueByCurrency = $subscriptionRevenueByCurrency->put(
            'INR',
            (float) ($subscriptionRevenueByCurrency['INR'] ?? 0) + $certificateOrderRevenue,
        );

        // Replaces the old "Users by Plan" breakdown - subscriptions no
        // longer gate anyone, so grouping by plan would show one
        // meaningless "Free: 100%" circle for nearly every tenant now.
        // Single vs bulk issuance is the equivalent pay-per-certificate
        // question: where is issuance actually coming from.
        $issuanceByType = Certificate::where('certificates.created_at', '>=', $periodStart)
            ->select(DB::raw('(certificate_batch_id IS NOT NULL) as is_bulk'), DB::raw('COUNT(*) as total'))
            ->groupBy('is_bulk')
            ->get()
            ->mapWithKeys(fn ($row) => [($row->is_bulk ? 'Bulk' : 'Single') => (int) $row->total]);

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

            $subscriptionTotal = UserSubscription::where('payment_status', 'completed')
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount_paid');

            $certificateOrderTotal = CertificateOrder::where('status', 'paid')
                ->whereBetween('paid_at', [$start, $end])
                ->sum('total_amount');

            return [$label => (float) $subscriptionTotal + (float) $certificateOrderTotal];
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

        [$donutSegments, $donutGradient, $totalIssuance] = $this->donut($issuanceByType);

        $maxRevenue = max($revenueSeries->max() ?: 1, 1);
        $revenuePoints = $revenueSeries->values();
        $pointCount = max($revenuePoints->count() - 1, 1);

        return view('livewire.admin.analytics-dashboard', [
            'period' => $this->period,
            'periodLabel' => "Last {$this->period} Days",
            'revenueByCurrency' => $revenueByCurrency,
            'issuanceByType' => $issuanceByType,
            'donutSegments' => $donutSegments,
            'donutGradient' => $donutGradient,
            'totalIssuance' => $totalIssuance,
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
     * @param  Collection<string, int|string>  $segments
     * @return array{0: list<array<string, mixed>>, 1: string, 2: int}
     */
    private function donut(Collection $segments): array
    {
        $colors = ['#d92727', '#151c27', '#dce2f3', '#cea62c', '#5e5e62'];
        $total = (int) $segments->sum();
        $donutSegments = [];
        $offset = 0;
        $colorIndex = 0;

        foreach ($segments as $name => $count) {
            if ($total === 0) {
                break;
            }
            $pct = ($count / $total) * 100;
            $color = $colors[$colorIndex % count($colors)];
            $donutSegments[] = [
                'name' => $name,
                'count' => $count,
                'percent' => round($pct),
                'color' => $color,
                'from' => $offset,
                'to' => $offset + $pct,
            ];
            $offset += $pct;
            $colorIndex++;
        }

        $donutGradient = $total > 0
            ? 'conic-gradient('.collect($donutSegments)->map(fn ($s) => "{$s['color']} {$s['from']}% {$s['to']}%")->implode(', ').')'
            : 'conic-gradient(#dce2f3 0% 100%)';

        return [$donutSegments, $donutGradient, $total];
    }
}

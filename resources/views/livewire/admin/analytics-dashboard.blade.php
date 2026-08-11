@php
use Illuminate\Support\Facades\Storage;

$maxIssuance = max(1, $issuanceSeries->max());
$totalRevenue = $revenueByCurrency->sum();

// Build SVG line path for revenue area chart
$revenuePathY = $revenuePoints->map(function ($value, $i) use ($pointCount, $maxRevenue) {
    $x = ($i / $pointCount) * 1000;
    $y = 200 - (($value / $maxRevenue) * 160);

    return round($x).','.round($y);
});
$linePath = $revenuePathY->isEmpty()
    ? '0,200'
    : $revenuePathY->map(fn ($p, $i) => ($i === 0 ? 'M' : 'L').$p)->implode(' ');
$areaPath = $linePath.' L1000,200 L0,200 Z';

$maxEmailDay = max(1, $emailSeries->max(fn ($day) => $day['sent'] + $day['failed']));
@endphp

<div wire:loading.class="opacity-60">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-md mb-lg">
        <div class="inline-flex items-center gap-xs rounded-lg border border-outline-variant bg-surface p-base">
            @foreach ([7, 30, 90] as $days)
                <button
                    type="button"
                    wire:click="$set('period', {{ $days }})"
                    @class([
                        'h-9 px-md rounded-md font-label-md text-label-md transition-colors',
                        'bg-primary-container text-on-primary shadow-sm' => $period === $days,
                        'text-on-surface-variant hover:text-on-surface hover:bg-surface-variant' => $period !== $days,
                    ])
                >
                    {{ $days }}d
                </button>
            @endforeach
        </div>
        <div class="flex items-center gap-xs text-on-surface-variant">
            <span class="material-symbols-outlined text-[18px]">calendar_today</span>
            <span class="font-label-md text-label-md">{{ $periodLabel }}</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-gutter mb-gutter">
        <div class="card-surface shadow-card p-lg">
            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Revenue (completed)</p>
            @forelse ($revenueByCurrency as $currency => $total)
                <p class="font-headline-md text-headline-md text-on-surface">
                    {{ $currency === 'INR' ? '₹' : '$' }}{{ number_format($total, 2) }}
                    <span class="font-label-sm text-label-sm text-on-surface-variant">{{ $currency }}</span>
                </p>
            @empty
                <p class="font-headline-md text-headline-md text-on-surface">—</p>
            @endforelse
        </div>
        <div class="card-surface shadow-card p-lg">
            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Certificates issued</p>
            <p class="font-headline-md text-headline-md text-on-surface">{{ number_format($totalCertificatesIssued) }}</p>
        </div>
        <div class="card-surface shadow-card p-lg">
            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Verification rate</p>
            <p class="font-headline-md text-headline-md text-on-surface">
                {{ $verificationRate !== null ? "{$verificationRate}%" : '—' }}
            </p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ number_format($totalVerifications) }} lookups total</p>
        </div>
        <div class="card-surface shadow-card p-lg">
            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider mb-xs">Email delivery rate</p>
            <p class="font-headline-md text-headline-md text-on-surface">
                {{ $emailDeliveryRate !== null ? "{$emailDeliveryRate}%" : '—' }}
            </p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ number_format($totalEmailsSent) }} sent &middot; {{ number_format($totalEmailsFailed) }} failed</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
        {{-- Monthly Revenue area chart --}}
        <div class="col-span-12 bg-surface border border-outline-variant rounded-xl p-lg shadow-card relative overflow-hidden">
            <div class="flex justify-between items-start mb-md relative z-10">
                <div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Monthly Revenue</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Total revenue from completed subscriptions.</p>
                </div>
                <div class="text-right">
                    @if ($totalRevenue > 0)
                        <span class="font-headline-xl text-headline-xl text-on-surface">
                            @foreach ($revenueByCurrency as $currency => $total)
                                {{ $currency === 'INR' ? '₹' : '$' }}{{ number_format($total, 0) }}@if (! $loop->last) / @endif
                            @endforeach
                        </span>
                    @else
                        <span class="font-headline-xl text-headline-xl text-on-surface">—</span>
                    @endif
                    <div class="flex items-center justify-end text-emerald-600 mt-1">
                        <span class="material-symbols-outlined text-[16px]">trending_up</span>
                        <span class="font-label-sm text-label-sm ml-1">{{ $verificationRate !== null ? "{$verificationRate}% verified" : 'No data yet' }}</span>
                    </div>
                </div>
            </div>
            <div class="h-64 w-full relative mt-xl">
                <svg class="absolute inset-0 w-full h-full" preserveAspectRatio="none" viewBox="0 0 1000 200">
                    <defs>
                        <linearGradient id="revenueGradient" x1="0%" x2="0%" y1="0%" y2="100%">
                            <stop offset="0%" stop-color="#d92727" stop-opacity="0.2"></stop>
                            <stop offset="100%" stop-color="#d92727" stop-opacity="0"></stop>
                        </linearGradient>
                    </defs>
                    <path d="{{ $areaPath }}" fill="url(#revenueGradient)"></path>
                    <path d="{{ $linePath }}" fill="none" stroke="#d92727" stroke-width="3"></path>
                </svg>
                <div class="absolute bottom-0 w-full flex justify-between font-label-sm text-label-sm text-on-surface-variant px-2 pb-2">
                    @foreach ($revenueSeries as $month => $amount)
                        <span>{{ $month }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Issuance by type donut --}}
        <div class="col-span-12 md:col-span-4 bg-surface border border-outline-variant rounded-xl p-lg shadow-card flex flex-col">
            <div class="mb-xl">
                <h3 class="font-headline-md text-headline-md text-on-surface">Issuance by Type</h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Single vs. bulk, {{ strtolower($periodLabel) }}.</p>
            </div>
            @if ($totalIssuance > 0)
                <div class="flex-1 flex items-center justify-center relative">
                    <div class="w-48 h-48 rounded-full relative" style="background: {{ $donutGradient }};">
                        <div class="absolute inset-4 bg-surface rounded-full flex items-center justify-center shadow-inner">
                            <div class="text-center">
                                <span class="block font-headline-lg text-headline-lg text-on-surface">{{ number_format($totalIssuance) }}</span>
                                <span class="block font-label-sm text-label-sm text-on-surface-variant">Total</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-xl flex flex-col gap-sm">
                    @foreach ($donutSegments as $segment)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-xs">
                                <span class="w-3 h-3 rounded-full" style="background-color: {{ $segment['color'] }}"></span>
                                <span class="font-label-md text-label-md text-on-surface">{{ $segment['name'] }}</span>
                            </div>
                            <span class="font-body-md text-body-md text-on-surface-variant">{{ $segment['percent'] }}%</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="font-body-md text-body-md text-on-surface-variant">No certificates issued in this period yet.</p>
            @endif
        </div>

        {{-- Issuance volume bar chart --}}
        <div class="col-span-12 md:col-span-8 bg-surface border border-outline-variant rounded-xl p-lg shadow-card flex flex-col">
            <div class="mb-xl flex justify-between items-start">
                <div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Certificate Issuance Volume</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Daily documents generated — {{ strtolower($periodLabel) }}.</p>
                </div>
                <span class="font-headline-lg text-headline-lg text-on-surface">{{ number_format($issuanceSeries->sum()) }}</span>
            </div>
            <div class="flex-1 flex items-end gap-[2px] h-64 relative pl-8">
                <div class="absolute left-0 top-0 h-full flex flex-col justify-between text-on-surface-variant font-label-sm text-label-sm pb-8 pr-2 border-r border-outline-variant">
                    <span>{{ number_format($maxIssuance) }}</span>
                    <span>{{ number_format((int) ($maxIssuance / 2)) }}</span>
                    <span>0</span>
                </div>
                @foreach ($issuanceSeries as $day => $count)
                    <div class="flex-1 group relative h-full flex items-end">
                        <div
                            class="w-full max-w-[32px] mx-auto rounded-t-sm transition-colors {{ $count === $maxIssuance && $count > 0 ? 'bg-primary-container shadow-[0_0_8px_rgba(217,39,39,0.4)]' : 'bg-secondary-container hover:bg-primary-container' }}"
                            style="height: {{ max(2, round(($count / $maxIssuance) * 100)) }}%"
                        ></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-on-surface text-on-primary text-[10px] rounded px-1.5 py-0.5 whitespace-nowrap z-10">
                            {{ \Illuminate\Support\Carbon::parse($day)->format('M j') }}: {{ $count }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between font-label-sm text-label-sm text-on-surface-variant pt-2 border-t border-outline-variant mt-2 pl-8">
                <span>{{ \Illuminate\Support\Carbon::parse($issuanceSeries->keys()->first())->format('M j') }}</span>
                <span>{{ \Illuminate\Support\Carbon::parse($issuanceSeries->keys()->last())->format('M j') }}</span>
            </div>
        </div>

        {{-- Email delivery: sent vs failed, last 30 days --}}
        <div id="email-delivery" class="col-span-12 bg-surface border border-outline-variant rounded-xl p-lg shadow-card flex flex-col scroll-mt-lg">
            <div class="mb-xl flex justify-between items-start">
                <div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Email Delivery</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Sent vs. failed — {{ strtolower($periodLabel) }}.</p>
                </div>
                <div class="flex items-center gap-lg">
                    <div class="flex items-center gap-xs">
                        <span class="w-2.5 h-2.5 rounded-full bg-primary-container"></span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant">Sent ({{ number_format($totalEmailsSent) }})</span>
                    </div>
                    <div class="flex items-center gap-xs">
                        <span class="w-2.5 h-2.5 rounded-full bg-error"></span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant">Failed ({{ number_format($totalEmailsFailed) }})</span>
                    </div>
                </div>
            </div>

            @if ($totalEmailsSent + $totalEmailsFailed > 0)
                <div class="flex items-end gap-[2px] h-64 relative pl-8">
                    <div class="absolute left-0 top-0 h-full flex flex-col justify-between text-on-surface-variant font-label-sm text-label-sm pb-8 pr-2 border-r border-outline-variant">
                        <span>{{ number_format($maxEmailDay) }}</span>
                        <span>{{ number_format((int) ($maxEmailDay / 2)) }}</span>
                        <span>0</span>
                    </div>
                    @foreach ($emailSeries as $day)
                        <div class="flex-1 group relative h-full flex flex-col justify-end items-center">
                            <div class="w-full max-w-[32px] mx-auto flex flex-col-reverse rounded-t-sm overflow-hidden" style="height: {{ max(2, round((($day['sent'] + $day['failed']) / $maxEmailDay) * 100)) }}%">
                                <div class="w-full bg-primary-container" style="height: {{ ($day['sent'] + $day['failed']) > 0 ? round(($day['sent'] / ($day['sent'] + $day['failed'])) * 100) : 0 }}%"></div>
                                <div class="w-full bg-error" style="height: {{ ($day['sent'] + $day['failed']) > 0 ? round(($day['failed'] / ($day['sent'] + $day['failed'])) * 100) : 0 }}%"></div>
                            </div>
                            <div class="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-on-surface text-on-primary text-[10px] rounded px-1.5 py-0.5 whitespace-nowrap z-10">
                                {{ \Illuminate\Support\Carbon::parse($day['day'])->format('M j') }}: {{ $day['sent'] }} sent, {{ $day['failed'] }} failed
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between font-label-sm text-label-sm text-on-surface-variant pt-2 border-t border-outline-variant mt-2 pl-8">
                    <span>{{ \Illuminate\Support\Carbon::parse($emailSeries->first()['day'])->format('M j') }}</span>
                    <span>{{ \Illuminate\Support\Carbon::parse($emailSeries->last()['day'])->format('M j') }}</span>
                </div>

                @if ($emailFailuresByType->isNotEmpty())
                    <div class="mt-xl pt-lg border-t border-outline-variant">
                        <h4 class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider mb-md">Top failing email types</h4>
                        <div class="flex flex-col gap-sm">
                            @foreach ($emailFailuresByType as $failure)
                                <div class="flex items-center justify-between">
                                    <span class="font-body-md text-body-md text-on-surface">{{ \Illuminate\Support\Str::headline(class_basename($failure->notification_class)) }}</span>
                                    <span class="font-label-sm text-label-sm text-error">{{ number_format($failure->total) }} failed</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <p class="font-body-md text-body-md text-on-surface-variant py-xl text-center">No emails have been sent yet.</p>
            @endif
        </div>

        {{-- Top templates table --}}
        <div class="col-span-12 bg-surface border border-outline-variant rounded-xl p-lg shadow-card">
            <div class="mb-xl flex justify-between items-center">
                <h3 class="font-headline-md text-headline-md text-on-surface">Top Performing Templates</h3>
                <a href="{{ route('admin.templates.index') }}" class="text-primary font-label-md text-label-md hover:underline">View All</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[600px]">
                    <thead>
                        <tr class="border-b border-outline-variant text-on-surface-variant font-label-sm text-label-sm uppercase tracking-wider">
                            <th class="pb-sm font-semibold">Template</th>
                            <th class="pb-sm font-semibold">Category</th>
                            <th class="pb-sm font-semibold">Issued</th>
                            <th class="pb-sm font-semibold text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-on-surface font-body-md text-body-md">
                        @forelse ($topTemplates as $template)
                            <tr class="border-b border-outline-variant last:border-0 hover:bg-surface-container-low transition-colors">
                                <td class="py-md">
                                    <div class="flex items-center gap-md">
                                        <div class="w-16 h-12 rounded bg-surface-container-low border border-outline-variant overflow-hidden shrink-0">
                                            @if ($template->thumbnail_path)
                                                <img src="{{ Storage::url($template->thumbnail_path) }}" alt="{{ $template->name }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center">
                                                    <span class="material-symbols-outlined text-on-surface-variant">description</span>
                                                </div>
                                            @endif
                                        </div>
                                        <span class="font-label-md text-label-md">{{ $template->name }}</span>
                                    </div>
                                </td>
                                <td class="py-md text-on-surface-variant">{{ $template->category ?? 'General' }}</td>
                                <td class="py-md">{{ number_format($template->certificates_count) }}</td>
                                <td class="py-md text-right">
                                    <span class="inline-flex items-center gap-xs bg-surface-container-low px-2 py-1 rounded-full border border-outline-variant">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $template->is_active ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                        <span class="font-label-sm text-label-sm {{ $template->is_active ? 'text-emerald-600' : 'text-amber-600' }}">{{ $template->is_active ? 'Active' : 'Draft' }}</span>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-xl text-center text-on-surface-variant">No templates yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Verification breakdown --}}
        <div class="col-span-12 bg-surface border border-outline-variant rounded-xl p-lg shadow-card">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Verification lookups by result</h3>
            @if ($totalVerifications > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-lg">
                    @foreach (['valid', 'revoked', 'expired', 'not_found'] as $result)
                        @php $count = $verificationCounts[$result] ?? 0; @endphp
                        <div>
                            <div class="flex justify-between font-body-sm text-body-sm text-on-surface-variant mb-1">
                                <span class="capitalize">{{ str_replace('_', ' ', $result) }}</span>
                                <span>{{ $count }} ({{ $totalVerifications > 0 ? round(($count / $totalVerifications) * 100) : 0 }}%)</span>
                            </div>
                            <div class="w-full bg-surface-container-highest rounded-full h-2">
                                <div class="h-2 rounded-full {{ $result === 'valid' ? 'bg-emerald-500' : ($result === 'not_found' ? 'bg-error' : 'bg-tertiary') }}"
                                     style="width: {{ $totalVerifications > 0 ? round(($count / $totalVerifications) * 100) : 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="font-body-md text-body-md text-on-surface-variant">No verification lookups recorded yet.</p>
            @endif
        </div>
    </div>
</div>

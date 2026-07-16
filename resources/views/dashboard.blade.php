@php
use Illuminate\Support\Facades\Storage;

$statusStyles = [
    'active' => ['dot' => 'bg-emerald-500', 'bg' => 'bg-surface/90', 'text' => 'text-on-surface', 'label' => 'Verified'],
    'expired' => ['dot' => 'bg-gray-400', 'bg' => 'bg-surface/90', 'text' => 'text-on-surface-variant', 'label' => 'Expired'],
    'revoked' => ['dot' => 'bg-red-500', 'bg' => 'bg-red-500/10', 'text' => 'text-red-600', 'label' => 'Revoked'],
];
$quotaRemaining = $activeSubscription?->remainingCertificates() ?? 0;
$quotaLimit = $activeSubscription?->certificates_limit ?? 0;
$quotaPercent = $quotaLimit > 0 ? round((($quotaLimit - $quotaRemaining) / $quotaLimit) * 100) : 0;
$strokeOffset = 100 - $quotaPercent;
@endphp

<x-layouts.user-shell title="Dashboard">
    <header class="flex flex-col md:flex-row md:items-center justify-between mb-xl gap-md">
        <div>
            <h1 class="font-headline-xl text-headline-xl text-on-background mb-xs">Welcome back, {{ $user->first_name }}</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">Here is an overview of your credentialing activity.</p>
        </div>
        <div class="flex flex-wrap items-center gap-sm">
            <a href="{{ route('bulk-upload.select-template') }}" class="bg-surface border border-outline-variant text-on-surface font-label-md text-label-md px-md py-sm rounded-lg hover:bg-surface-container-low transition-colors shadow-sm flex items-center gap-xs">
                <span class="material-symbols-outlined text-[18px]">upload_file</span>
                Bulk Upload
            </a>
            <a href="{{ route('templates.index') }}" class="bg-primary-container text-on-primary font-label-md text-label-md px-md py-sm rounded-lg hover:bg-primary transition-colors shadow-sm flex items-center gap-xs">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Create Certificate
            </a>
        </div>
    </header>

    <section class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-2xl">
        <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-[0px_4px_12px_rgba(0,0,0,0.02)] flex flex-col justify-between h-[160px] hover:shadow-[0px_4px_12px_rgba(0,0,0,0.05)] transition-shadow">
            <div class="flex items-center gap-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[20px]">workspace_premium</span>
                <h2 class="font-label-md text-label-md">Certificates Issued</h2>
            </div>
            <div>
                <div class="font-headline-xl text-headline-xl text-on-background font-black tracking-tight">{{ number_format($certificateCount) }}</div>
                <div class="font-body-sm text-body-sm text-secondary mt-1">{{ $user->organization_name }}</div>
            </div>
        </div>

        <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-[0px_4px_12px_rgba(0,0,0,0.02)] flex flex-row items-center justify-between h-[160px] hover:shadow-[0px_4px_12px_rgba(0,0,0,0.05)] transition-shadow">
            <div class="flex flex-col h-full justify-between">
                <div class="flex items-center gap-sm text-on-surface-variant">
                    <span class="material-symbols-outlined text-[20px]">data_usage</span>
                    <h2 class="font-label-md text-label-md">Quota Remaining</h2>
                </div>
                <div>
                    <div class="font-headline-lg text-headline-lg text-on-background font-bold">{{ number_format($quotaRemaining) }}</div>
                    <div class="font-body-sm text-body-sm text-secondary mt-1">
                        @if ($activeSubscription)
                            out of {{ number_format($quotaLimit) }} credits
                        @else
                            No active plan
                        @endif
                    </div>
                </div>
            </div>
            @if ($activeSubscription)
                <div class="relative w-20 h-20 flex items-center justify-center">
                    <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
                        <circle class="stroke-surface-container-high" cx="18" cy="18" fill="none" r="16" stroke-width="3"></circle>
                        <circle class="stroke-primary-container" cx="18" cy="18" fill="none" r="16" stroke-dasharray="100" stroke-dashoffset="{{ $strokeOffset }}" stroke-linecap="round" stroke-width="3"></circle>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center font-label-md text-label-md font-bold text-on-surface">{{ $quotaPercent }}%</div>
                </div>
            @endif
        </div>

        <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-[0px_4px_12px_rgba(0,0,0,0.02)] flex flex-col justify-between h-[160px] relative overflow-hidden hover:shadow-[0px_4px_12px_rgba(0,0,0,0.05)] transition-shadow">
            <div class="absolute -right-4 -top-4 w-24 h-24 bg-surface-container-high rounded-full opacity-50"></div>
            <div class="relative z-10 flex items-center gap-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[20px]">stars</span>
                <h2 class="font-label-md text-label-md">Current Plan</h2>
            </div>
            <div class="relative z-10">
                @if ($activeSubscription)
                    <div class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-primary-container/10 text-primary-container font-label-sm text-label-sm border border-primary-container/20 mb-2">
                        {{ $activeSubscription->subscription->name }}
                    </div>
                    <div class="font-headline-md text-headline-md text-on-background capitalize">{{ $activeSubscription->billing_period }} billing</div>
                @else
                    <div class="font-headline-md text-headline-md text-on-background">Free tier</div>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Upgrade for more capacity</p>
                @endif
                <a href="{{ route('subscriptions.index') }}" class="font-label-sm text-label-sm text-secondary hover:text-primary transition-colors flex items-center gap-1 mt-1 group">
                    Manage subscription
                    <span class="material-symbols-outlined text-[14px] group-hover:translate-x-0.5 transition-transform">arrow_forward</span>
                </a>
            </div>
        </div>
    </section>

    <section>
        <div class="flex items-center justify-between mb-lg border-b border-outline-variant pb-xs">
            <h2 class="font-headline-md text-headline-md text-on-background">Recent Certificates</h2>
            <a href="{{ route('certificates.index') }}" class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors flex items-center gap-1">
                View All
            </a>
        </div>

        @if ($recentCertificates->isEmpty())
            <div class="bg-surface border border-outline-variant rounded-xl p-2xl text-center shadow-sm">
                <div class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center mx-auto mb-md">
                    <span class="material-symbols-outlined text-primary text-[32px]">workspace_premium</span>
                </div>
                <p class="font-body-md text-body-md text-on-surface-variant mb-md">No certificates issued yet — pick a template to get started.</p>
                <a href="{{ route('templates.index') }}" class="inline-flex items-center gap-xs h-11 px-lg bg-primary-container text-on-primary rounded-lg font-label-md text-label-md font-semibold hover:bg-primary transition-colors">
                    Browse Templates
                </a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter">
                @foreach ($recentCertificates as $certificate)
                    @php $status = $statusStyles[$certificate->displayStatus()]; @endphp
                    <article class="bg-surface border border-outline-variant rounded-xl overflow-hidden shadow-[0px_4px_12px_rgba(0,0,0,0.02)] hover:shadow-[0px_4px_12px_rgba(0,0,0,0.08)] transition-all group">
                        <a href="{{ route('certificates.show', $certificate) }}" class="block">
                            <div class="aspect-[4/3] bg-surface-container-low w-full relative border-b border-outline-variant overflow-hidden flex items-center justify-center">
                                @if ($certificate->image_path && Storage::disk('local')->exists($certificate->image_path))
                                    <img src="{{ route('certificates.image', $certificate) }}" alt="{{ $certificate->title }}" class="w-full h-full object-cover">
                                @elseif ($certificate->template?->thumbnail_path)
                                    <img src="{{ Storage::url($certificate->template->thumbnail_path) }}" alt="{{ $certificate->title }}" class="w-full h-full object-cover">
                                @else
                                    <span class="material-symbols-outlined text-[48px] text-on-surface-variant/20">history_edu</span>
                                @endif
                                <div class="absolute top-sm right-sm {{ $status['bg'] }} backdrop-blur-sm px-2 py-1 rounded border border-outline-variant flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $status['dot'] }}"></span>
                                    <span class="font-label-sm text-label-sm {{ $status['text'] }}">{{ $status['label'] }}</span>
                                </div>
                            </div>
                            <div class="p-md">
                                <h3 class="font-headline-md text-headline-md text-on-background truncate">{{ $certificate->recipient_name }}</h3>
                                <p class="font-body-sm text-body-sm text-on-surface-variant mb-3 truncate">{{ $certificate->title }}</p>
                                <div class="flex items-center justify-between text-secondary">
                                    <div class="font-label-sm text-label-sm flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                                        {{ $certificate->date_of_issue->format('M d, Y') }}
                                    </div>
                                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant group-hover:text-primary transition-colors">download</span>
                                </div>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.user-shell>

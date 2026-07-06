@php
use Illuminate\Support\Facades\Storage;
@endphp

<x-layouts.admin-shell title="Dashboard">
    <header class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-xl">
        <div>
            <h2 class="font-headline-xl text-headline-xl text-on-surface">Overview</h2>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                Operational metrics and recent activity across the platform.
            </p>
        </div>
        <div class="flex items-center gap-md">
            <a href="{{ route('admin.analytics.index') }}" class="w-10 h-10 rounded-xl bg-surface border border-outline-variant flex items-center justify-center text-on-surface-variant shadow-[0px_4px_12px_rgba(0,0,0,0.05)] hover:text-primary transition-colors" title="Analytics">
                <span class="material-symbols-outlined">monitoring</span>
            </a>
            <a href="{{ route('admin.users.unapproved') }}" class="w-10 h-10 rounded-xl bg-surface border border-outline-variant flex items-center justify-center text-on-surface-variant shadow-[0px_4px_12px_rgba(0,0,0,0.05)] hover:text-primary transition-colors" title="Pending approvals">
                <span class="material-symbols-outlined">filter_list</span>
            </a>
        </div>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-gutter">
        <div class="col-span-1 md:col-span-4 bg-surface border border-outline-variant rounded-xl p-lg flex flex-col justify-between shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-start mb-md">
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Total Certificates</span>
                <div class="w-8 h-8 rounded-full bg-surface-container-low flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">workspace_premium</span>
                </div>
            </div>
            <div>
                <div class="font-headline-xl text-headline-xl text-on-surface mb-xs">{{ number_format($totalCertificateCount) }}</div>
                <div class="flex items-center gap-xs font-label-sm text-label-sm text-primary-container">
                    <span class="material-symbols-outlined text-[14px]">trending_up</span>
                    <span>{{ number_format($certificatesIssuedThisMonth) }} this month</span>
                </div>
            </div>
            <div class="mt-md h-12 w-full">
                <svg class="w-full h-full text-primary-container" preserveAspectRatio="none" viewBox="0 0 100 30">
                    <path d="M0,25 C10,20 20,28 30,15 C40,5 50,18 60,10 C70,2 80,12 90,5 L100,0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" vector-effect="non-scaling-stroke"></path>
                </svg>
            </div>
        </div>

        <div class="col-span-1 md:col-span-4 bg-surface border border-outline-variant rounded-xl p-lg flex flex-col justify-between shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-start mb-md">
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Active Users</span>
                <div class="w-8 h-8 rounded-full bg-surface-container-low flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">group</span>
                </div>
            </div>
            <div>
                <div class="font-headline-xl text-headline-xl text-on-surface mb-xs">{{ number_format($activeUserCount) }}</div>
                <div class="flex items-center gap-xs font-label-sm text-label-sm text-primary-container">
                    <span class="material-symbols-outlined text-[14px]">trending_up</span>
                    <span>{{ number_format($pendingApprovalCount) }} pending approval</span>
                </div>
            </div>
            <div class="mt-md h-12 w-full">
                <svg class="w-full h-full text-primary-container" preserveAspectRatio="none" viewBox="0 0 100 30">
                    <path d="M0,28 C15,25 25,15 35,20 C45,25 55,5 65,10 C75,15 85,2 100,5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" vector-effect="non-scaling-stroke"></path>
                </svg>
            </div>
        </div>

        <div class="col-span-1 md:col-span-4 bg-surface border border-outline-variant rounded-xl p-lg flex flex-col justify-between shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-start mb-md">
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Paying Subscribers</span>
                <div class="w-8 h-8 rounded-full bg-surface-container-low flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]">payments</span>
                </div>
            </div>
            <div>
                <div class="font-headline-xl text-headline-xl text-on-surface mb-xs">{{ number_format($activeSubscriberCount) }}</div>
                <div class="flex items-center gap-xs font-label-sm text-label-sm text-primary-container">
                    <span class="material-symbols-outlined text-[14px]">trending_up</span>
                    <span>Active subscriptions</span>
                </div>
            </div>
            <div class="mt-md h-12 w-full">
                <svg class="w-full h-full text-primary-container" preserveAspectRatio="none" viewBox="0 0 100 30">
                    <path d="M0,20 C20,15 30,25 40,10 C50,-5 60,15 70,5 C80,-5 90,5 100,0" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2" vector-effect="non-scaling-stroke"></path>
                </svg>
            </div>
        </div>

        <div class="col-span-1 md:col-span-5 bg-surface border border-outline-variant rounded-xl p-lg flex flex-col shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-center mb-md border-b border-outline-variant pb-md">
                <h3 class="font-headline-md text-headline-md text-on-surface flex items-center gap-sm">
                    Pending Approvals
                    @if ($pendingApprovalCount > 0)
                        <span class="inline-flex items-center justify-center bg-tertiary-container text-on-tertiary-container text-[10px] font-bold px-2 py-0.5 rounded-full">{{ $pendingApprovalCount }}</span>
                    @endif
                </h3>
                <a href="{{ route('admin.users.unapproved') }}" class="text-primary font-label-sm text-label-sm hover:underline">View All</a>
            </div>
            <div class="flex flex-col gap-sm overflow-y-auto max-h-[300px] pr-2">
                @forelse ($pendingUsers as $user)
                    <div class="flex items-center justify-between p-sm rounded-lg hover:bg-surface-container-low transition-colors">
                        <div class="flex items-center gap-sm min-w-0">
                            <div class="w-10 h-10 rounded-full bg-surface-variant border border-outline-variant flex items-center justify-center font-label-sm text-primary shrink-0">
                                {{ strtoupper(substr($user->first_name, 0, 1).substr($user->last_name, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="font-label-md text-label-md text-on-surface truncate">{{ $user->name }}</div>
                                <div class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ $user->organization_name }}</div>
                            </div>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <form method="POST" action="{{ route('admin.users.reject', $user) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-8 h-8 rounded-lg border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-error hover:border-error transition-colors" title="Reject">
                                    <span class="material-symbols-outlined text-[16px]">close</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.users.approve', $user) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="w-8 h-8 rounded-lg bg-surface-container-high flex items-center justify-center text-primary hover:bg-primary-container hover:text-on-primary-container transition-colors" title="Approve">
                                    <span class="material-symbols-outlined text-[16px]">check</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="font-body-md text-body-md text-on-surface-variant text-center py-lg">No pending approvals right now.</p>
                @endforelse
            </div>
        </div>

        <div class="col-span-1 md:col-span-7 bg-surface border border-outline-variant rounded-xl p-lg flex flex-col shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-center mb-md border-b border-outline-variant pb-md">
                <h3 class="font-headline-md text-headline-md text-on-surface">Top Templates</h3>
                <div class="font-label-sm text-label-sm text-on-surface-variant">All time</div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                @forelse ($topTemplates as $template)
                    @php $pct = $maxTemplateUsage > 0 ? round(($template->certificates_count / $maxTemplateUsage) * 100) : 0; @endphp
                    <div class="flex gap-md p-sm border border-outline-variant rounded-lg items-center">
                        <div class="w-16 h-20 bg-surface-container-low rounded border border-outline-variant flex items-center justify-center flex-shrink-0 relative overflow-hidden">
                            @if ($template->thumbnail_path)
                                <img src="{{ Storage::url($template->thumbnail_path) }}" alt="{{ $template->name }}" class="w-full h-full object-cover">
                            @else
                                <span class="material-symbols-outlined text-outline">description</span>
                            @endif
                            <div class="absolute bottom-0 left-0 w-full h-1 bg-primary"></div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-label-md text-label-md text-on-surface mb-1 truncate">{{ $template->name }}</div>
                            <div class="font-body-sm text-body-sm text-on-surface-variant mb-2">Used {{ number_format($template->certificates_count) }} times</div>
                            <div class="w-full bg-surface-variant h-1.5 rounded-full overflow-hidden">
                                <div class="bg-primary h-full" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="col-span-full font-body-md text-body-md text-on-surface-variant text-center py-lg">No template usage yet.</p>
                @endforelse
            </div>
        </div>

        <div class="col-span-1 md:col-span-12 bg-surface border border-outline-variant rounded-xl flex flex-col overflow-hidden shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="p-lg border-b border-outline-variant flex justify-between items-center bg-surface">
                <h3 class="font-headline-md text-headline-md text-on-surface">Recent Issuances</h3>
                <a href="{{ route('admin.certificates.index') }}" class="flex items-center gap-xs font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">
                    View all
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </div>
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr class="bg-surface-bright border-b border-outline-variant">
                            <th class="px-lg py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider font-semibold">Recipient</th>
                            <th class="px-lg py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider font-semibold">Certificate ID</th>
                            <th class="px-lg py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider font-semibold">Template</th>
                            <th class="px-lg py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider font-semibold">Date</th>
                            <th class="px-lg py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider font-semibold">Status</th>
                            <th class="px-lg py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="font-body-md text-body-md text-on-surface">
                        @forelse ($recentCertificates as $certificate)
                            @php $status = $certificate->displayStatus(); @endphp
                            <tr class="border-b border-outline-variant last:border-0 hover:bg-surface-container-low transition-colors">
                                <td class="px-lg py-md">
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-surface-variant flex items-center justify-center font-label-sm text-primary shrink-0">
                                            {{ strtoupper(substr($certificate->recipient_name, 0, 2)) }}
                                        </div>
                                        {{ $certificate->recipient_name }}
                                    </div>
                                </td>
                                <td class="px-lg py-md font-mono text-xs text-on-surface-variant">{{ $certificate->verification_code }}</td>
                                <td class="px-lg py-md">{{ $certificate->template?->name ?? '—' }}</td>
                                <td class="px-lg py-md text-on-surface-variant">{{ $certificate->date_of_issue->format('M d, Y') }}</td>
                                <td class="px-lg py-md">
                                    <x-status-badge :status="$status">{{ ucfirst($status) }}</x-status-badge>
                                </td>
                                <td class="px-lg py-md text-right">
                                    <a href="{{ route('admin.certificates.index', ['search' => $certificate->verification_code]) }}" class="text-on-surface-variant hover:text-primary transition-colors inline-flex">
                                        <span class="material-symbols-outlined text-[20px]">more_vert</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-lg py-xl text-center font-body-md text-body-md text-on-surface-variant">No certificates issued yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.admin-shell>

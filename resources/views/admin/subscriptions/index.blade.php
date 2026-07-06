<x-layouts.admin-shell title="Subscription Plans">
    <x-page-header
        title="Subscription Plans"
        description="Manage your product offerings, pricing, and feature limits."
    >
        <x-slot:actions>
            <a href="{{ route('admin.subscriptions.create') }}" class="btn-primary h-11">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Create Plan
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-gutter">
        @foreach ($plans as $plan)
            <div class="card-surface bento-shadow p-lg hover:shadow-[0px_4px_12px_rgba(0,0,0,0.08)] transition-shadow {{ $plan->is_default_free_plan ? 'border-l-4 border-l-primary' : '' }}">
                <div class="flex justify-between items-start mb-md">
                    <div>
                        <div class="flex items-center gap-sm mb-xs">
                            <h3 class="font-headline-md text-headline-md text-on-surface">{{ $plan->name }}</h3>
                            <x-status-badge :status="$plan->is_active ? 'active' : 'draft'">
                                {{ $plan->is_active ? 'Active' : 'Inactive' }}
                            </x-status-badge>
                        </div>
                        @if ($plan->is_default_free_plan)
                            <span class="font-label-sm text-label-sm text-primary">Default Free Plan</span>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="font-headline-lg text-headline-lg font-bold text-on-surface">
                            {{ $plan->isFree() ? 'Free' : '₹'.number_format($plan->cost_month_inr, 0) }}
                        </div>
                        @unless ($plan->isFree())
                            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase">/ month</p>
                        @endunless
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-md mb-lg py-md border-t border-b border-outline-variant">
                    <div>
                        <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Certificates</p>
                        <p class="font-body-md text-body-md font-medium text-on-surface">{{ $plan->certificates_per_month }} / mo</p>
                    </div>
                    <div>
                        <p class="font-label-sm text-label-sm text-on-surface-variant mb-1">Recipients</p>
                        <p class="font-body-md text-body-md font-medium text-on-surface">{{ $plan->users_per_month }} / mo</p>
                    </div>
                </div>

                <div class="flex items-center gap-sm">
                    <a href="{{ route('admin.subscriptions.edit', $plan) }}" class="btn-secondary flex-1 h-10">
                        Edit Plan
                    </a>
                    <form method="POST" action="{{ route('admin.subscriptions.toggle-status', $plan) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn-secondary h-10 px-md">
                            {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.admin-shell>

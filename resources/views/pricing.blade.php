@php
$currencySymbol = $currency === 'INR' ? '₹' : '$';
$paidPlans = $plans->filter(fn ($p) => ! $p->isFree());
$popularPlanId = $paidPlans->sortBy('sort_order')->first()?->id;
@endphp

<x-layouts.user-shell title="Pricing">
    <div class="max-w-5xl mx-auto">
        <section class="text-center mb-2xl max-w-2xl mx-auto pt-xl fade-up">
            <h1 class="font-headline-xl text-headline-xl text-on-surface mb-sm">Transparent pricing for every team</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mb-xl">Choose the plan that fits your certification needs. No hidden fees. Upgrade or cancel anytime.</p>

            <div class="flex items-center justify-center gap-md">
                <span class="font-label-md text-label-md text-on-surface">Monthly</span>
                <button
                    type="button"
                    id="billing-toggle"
                    role="switch"
                    aria-checked="true"
                    data-yearly="true"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 bg-primary-container"
                >
                    <span id="billing-toggle-thumb" class="translate-x-5 pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                </button>
                <span class="font-label-md text-label-md text-on-surface-variant flex items-center gap-xs">
                    Yearly
                    <span class="bg-primary-fixed text-on-primary-fixed px-2 py-0.5 rounded-full text-[10px] font-bold">SAVE 20%</span>
                </span>
            </div>
            <p class="mt-xs font-body-sm text-body-sm text-on-surface-variant">Currency: {{ $currency }} ({{ $currencySymbol }})</p>
        </section>

        @if (session('quota_message'))
            <div class="max-w-xl mx-auto mb-xl bg-tertiary-container/20 text-tertiary rounded-lg px-md py-sm font-body-sm text-body-sm text-center border border-tertiary-container/30">
                {{ session('quota_message') }}
            </div>
        @endif

        <section class="grid grid-cols-1 md:grid-cols-3 gap-lg lg:gap-gutter mb-2xl items-stretch fade-up">
            @foreach ($plans as $plan)
                @php
                    $isCurrent = $activeSubscription && $activeSubscription->subscription_id === $plan->id;
                    $isFree = $plan->isFree();
                    $isPopular = ! $isFree && $plan->id === $popularPlanId;
                    $monthlyPrice = $plan->priceFor('monthly', $currency);
                    $yearlyPrice = $plan->priceFor('yearly', $currency);
                @endphp

                <div @class([
                    'rounded-xl p-lg flex flex-col transition-shadow',
                    'bg-surface border border-outline-variant shadow-sm hover:shadow-md' => $isFree,
                    'bg-surface rounded-xl border-2 border-primary-container flex flex-col shadow-[0_8px_24px_rgba(217,39,39,0.15)] relative transform md:-translate-y-4' => $isPopular,
                    'bg-surface-container-low border border-outline-variant shadow-sm hover:shadow-md' => ! $isFree && ! $isPopular,
                ])>
                    @if ($isPopular)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-primary-container text-on-primary rounded-full px-4 py-1 font-label-sm text-label-sm font-bold tracking-wider uppercase">
                            Most Popular
                        </span>
                    @endif

                    <div @class(['mb-md', 'mt-sm' => $isPopular])>
                        <h2 class="font-headline-lg text-headline-lg text-on-surface flex items-center gap-xs">
                            {{ $plan->name }}
                            @if ($isPopular)
                                <span class="material-symbols-outlined text-primary-container text-[20px]">workspace_premium</span>
                            @endif
                        </h2>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">{{ $plan->description }}</p>
                    </div>

                    <div class="mb-xl">
                        @if ($isFree)
                            <span class="text-4xl font-black text-on-surface tracking-tighter">Free</span>
                        @else
                            <div data-price-monthly class="hidden">
                                <span class="text-4xl font-black text-on-surface tracking-tighter">{{ $currencySymbol }}{{ number_format($monthlyPrice, 0) }}</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">/mo</span>
                            </div>
                            <div data-price-yearly>
                                <span class="text-4xl font-black text-on-surface tracking-tighter">{{ $currencySymbol }}{{ number_format($yearlyPrice / 12, 0) }}</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">/mo</span>
                            </div>
                            <p data-price-yearly class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                                Billed {{ $currencySymbol }}{{ number_format($yearlyPrice, 0) }} <span data-billing-period>yearly</span>
                            </p>
                            <p data-price-monthly class="hidden font-body-sm text-body-sm text-on-surface-variant mt-1">
                                Billed <span data-billing-period>monthly</span>
                            </p>
                        @endif
                    </div>

                    @guest
                        <a href="{{ route('login') }}" class="w-full py-2 px-4 rounded-lg font-label-md text-label-md border border-outline-variant text-on-surface bg-surface hover:bg-surface-variant transition-colors mb-xl text-center">
                            Login to Subscribe
                        </a>
                    @else
                        @if ($isCurrent)
                            <button disabled class="w-full py-2 px-4 rounded-lg font-label-md text-label-md bg-surface-variant text-on-surface-variant cursor-not-allowed mb-xl">
                                Current Plan
                            </button>
                        @elseif ($isFree)
                            <form method="POST" action="{{ route('purchase.process', $plan) }}" class="mb-xl">
                                @csrf
                                <button type="submit" class="w-full py-2 px-4 rounded-lg font-label-md text-label-md border border-outline-variant text-on-surface bg-surface hover:bg-surface-variant transition-colors">
                                    Get Started
                                </button>
                            </form>
                        @else
                            <a href="{{ route('purchase.show', $plan) }}" class="w-full py-2 px-4 rounded-lg font-label-md text-label-md {{ $isPopular ? 'bg-primary-container text-on-primary hover:bg-surface-tint shadow-sm' : 'border border-outline-variant text-on-surface bg-surface hover:bg-surface-variant' }} transition-all mb-xl flex justify-center items-center gap-xs">
                                {{ $activeSubscription ? 'Upgrade Plan' : ($isPopular ? 'Start Free Trial' : 'Choose Plan') }}
                            </a>
                        @endif
                    @endguest

                    <div class="flex-grow">
                        <p class="font-label-sm text-label-sm text-on-surface uppercase tracking-wider mb-sm">
                            {{ $isPopular ? 'Everything in Free, plus:' : 'Features' }}
                        </p>
                        <ul class="space-y-sm">
                            <li class="flex items-start gap-xs font-body-md text-body-md {{ $isPopular ? 'text-on-surface' : 'text-on-surface-variant' }}">
                                <span class="material-symbols-outlined {{ $isPopular ? 'text-primary-container' : 'text-primary-container' }} text-[20px]">check</span>
                                {{ number_format($plan->certificates_per_month) }} certificates / month
                            </li>
                            <li class="flex items-start gap-xs font-body-md text-body-md {{ $isPopular ? 'text-on-surface' : 'text-on-surface-variant' }}">
                                <span class="material-symbols-outlined text-primary-container text-[20px]">check</span>
                                {{ number_format($plan->users_per_month) }} recipients / month
                            </li>
                            @if ($isPopular)
                                <li class="flex items-start gap-xs font-body-md text-body-md text-on-surface">
                                    <span class="material-symbols-outlined text-primary-container text-[20px]">check</span>
                                    Bulk CSV Upload
                                </li>
                                <li class="flex items-start gap-xs font-body-md text-body-md text-on-surface">
                                    <span class="material-symbols-outlined text-primary-container text-[20px]">check</span>
                                    Priority Email Support
                                </li>
                            @elseif ($isFree)
                                <li class="flex items-start gap-xs font-body-md text-body-md text-on-surface-variant">
                                    <span class="material-symbols-outlined text-primary-container text-[20px]">check</span>
                                    Standard Templates
                                </li>
                                <li class="flex items-start gap-xs font-body-md text-body-md text-on-surface-variant">
                                    <span class="material-symbols-outlined text-primary-container text-[20px]">check</span>
                                    Community Support
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            @endforeach

            @if ($plans->count() < 3)
                <div class="bg-surface-container-low rounded-xl border border-outline-variant p-lg flex flex-col shadow-sm hover:shadow-md transition-shadow">
                    <div class="mb-md">
                        <h2 class="font-headline-lg text-headline-lg text-on-surface">Enterprise</h2>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">Dedicated infrastructure and SLA.</p>
                    </div>
                    <div class="mb-xl flex items-center h-[52px]">
                        <span class="text-3xl font-black text-on-surface tracking-tighter">Custom</span>
                    </div>
                    <a href="mailto:sales@proudify.test" class="w-full py-2 px-4 rounded-lg font-label-md text-label-md border border-outline-variant text-on-surface bg-surface hover:bg-surface-variant transition-colors mb-xl text-center">
                        Contact Sales
                    </a>
                    <div class="flex-grow">
                        <p class="font-label-sm text-label-sm text-on-surface uppercase tracking-wider mb-sm">Everything in Pro, plus:</p>
                        <ul class="space-y-sm">
                            <li class="flex items-start gap-xs font-body-md text-body-md text-on-surface-variant">
                                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">check</span>
                                Dedicated Account Manager
                            </li>
                            <li class="flex items-start gap-xs font-body-md text-body-md text-on-surface-variant">
                                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">check</span>
                                API Access
                            </li>
                            <li class="flex items-start gap-xs font-body-md text-body-md text-on-surface-variant">
                                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">check</span>
                                Custom SSO Integration
                            </li>
                            <li class="flex items-start gap-xs font-body-md text-body-md text-on-surface-variant">
                                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">check</span>
                                99.99% Uptime SLA
                            </li>
                        </ul>
                    </div>
                </div>
            @endif
        </section>

        <section class="max-w-3xl mx-auto pt-xl border-t border-outline-variant fade-up">
            <h3 class="font-headline-lg text-headline-lg text-on-surface text-center mb-xl">Frequently Asked Questions</h3>
            <div class="space-y-sm">
                <div class="border border-outline-variant rounded-lg bg-surface overflow-hidden">
                    <button type="button" data-faq-toggle class="w-full text-left p-md font-headline-md text-headline-md text-on-surface flex justify-between items-center hover:bg-surface-container-low transition-colors focus:outline-none">
                        Can I change my plan later?
                        <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-200">expand_more</span>
                    </button>
                    <div class="hidden p-md pt-0 font-body-md text-body-md text-on-surface-variant border-t border-outline-variant bg-surface-container-lowest">
                        Yes, you can upgrade or downgrade your plan at any time from your account settings. Prorated charges or credits will be applied automatically.
                    </div>
                </div>
                <div class="border border-outline-variant rounded-lg bg-surface overflow-hidden">
                    <button type="button" data-faq-toggle class="w-full text-left p-md font-headline-md text-headline-md text-on-surface flex justify-between items-center hover:bg-surface-container-low transition-colors focus:outline-none">
                        What happens if I exceed my monthly certificate limit on the Free plan?
                        <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-200 rotate-180">expand_more</span>
                    </button>
                    <div class="p-md pt-md font-body-md text-body-md text-on-surface-variant border-t border-outline-variant bg-surface-container-lowest">
                        If you reach your limit, you won't be able to generate new certificates until the next billing cycle. You will receive a notification before you hit the limit, giving you time to upgrade to a paid plan if needed.
                    </div>
                </div>
                <div class="border border-outline-variant rounded-lg bg-surface overflow-hidden">
                    <button type="button" data-faq-toggle class="w-full text-left p-md font-headline-md text-headline-md text-on-surface flex justify-between items-center hover:bg-surface-container-low transition-colors focus:outline-none">
                        Do you offer discounts for non-profits or educational institutions?
                        <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-200">expand_more</span>
                    </button>
                    <div class="hidden p-md pt-0 font-body-md text-body-md text-on-surface-variant border-t border-outline-variant bg-surface-container-lowest">
                        Yes, we offer a 30% discount for registered non-profits and educational institutions. Please contact our sales team with proof of your status to apply the discount to your account.
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-layouts.user-shell>

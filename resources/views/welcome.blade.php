<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
@php use Illuminate\Support\Facades\Storage; @endphp
<head>
    <x-head title="Verifiable Credentials" />
</head>
<body class="bg-background text-on-background font-body-md antialiased selection:bg-primary selection:text-on-primary min-h-screen flex flex-col">
    <header class="sticky top-0 z-50 bg-surface/90 backdrop-blur-md border-b border-outline-variant">
        <div class="flex justify-between items-center w-full px-margin max-w-[1200px] mx-auto h-[72px]">
            <x-application-logo variant="brand" href="/" />
            <nav class="hidden md:flex items-center gap-xl">
                <a href="#features" class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">Features</a>
                <a href="#templates" class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">Templates</a>
                <a href="#pricing" class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">Pricing</a>
            </nav>
            <div class="flex items-center gap-md">
                <a href="{{ route('login') }}" class="hidden md:inline-flex font-label-md text-label-md text-on-surface-variant hover:text-on-surface transition-colors">Login</a>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-md py-sm bg-primary-container text-on-primary font-label-md text-label-md rounded-lg shadow-sm hover:bg-primary transition-colors">
                    Get Started
                </a>
            </div>
        </div>
    </header>

    <main class="flex-grow">
        <section class="relative pt-[96px] pb-[96px] px-margin overflow-hidden bg-surface-container-low">
            <div class="absolute inset-0 -z-0 pointer-events-none" style="background-image: radial-gradient(circle, #e5e7eb 1px, transparent 1px); background-size: 28px 28px; mask-image: linear-gradient(to bottom, black, transparent 70%); opacity: 0.4;"></div>
            <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-primary-fixed rounded-full blur-[120px] opacity-20 -z-0 pointer-events-none transform translate-x-1/3 -translate-y-1/4"></div>
            <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-tertiary-fixed rounded-full blur-[110px] opacity-10 -z-0 pointer-events-none transform -translate-x-1/3 translate-y-1/4"></div>

            <div class="max-w-[1200px] mx-auto grid grid-cols-1 md:grid-cols-2 gap-2xl items-center relative z-10">
                <div class="flex flex-col gap-lg">
                    <div class="inline-flex items-center gap-xs px-sm py-base bg-surface border border-outline-variant rounded-full w-max shadow-sm">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest">Proudify Platform</span>
                    </div>

                    <h1 class="font-headline-xl text-headline-xl md:text-[52px] md:leading-[56px] text-on-surface text-balance font-bold tracking-tight">
                        Certificates people can
                        <span class="bg-gradient-to-r from-primary to-primary-container bg-clip-text text-transparent">actually verify.</span>
                    </h1>

                    <p class="font-body-md text-body-md text-on-surface-variant max-w-[480px] leading-relaxed">
                        Design once, issue to thousands in one upload, and give every recipient a link anyone can check in seconds.
                    </p>

                    <div class="flex flex-wrap items-center gap-md pt-xs">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-xs px-lg py-sm bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-lg shadow-primary/20 hover:bg-primary-container transition-all hover:scale-[1.02] hover:shadow-xl">
                            Start Free Trial
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </a>
                        <a href="#pricing" class="inline-flex items-center justify-center px-lg py-sm bg-surface border border-outline text-on-surface font-label-md text-label-md rounded-lg hover:bg-surface-variant transition-colors">
                            See Pricing
                        </a>
                    </div>

                    <div class="flex items-center gap-sm pt-md">
                        <div class="flex -space-x-3">
                            <div class="w-9 h-9 rounded-full bg-surface-variant border-2 border-surface-container-low flex items-center justify-center font-label-sm text-label-sm text-on-surface-variant">AK</div>
                            <div class="w-9 h-9 rounded-full bg-surface-variant border-2 border-surface-container-low flex items-center justify-center font-label-sm text-label-sm text-on-surface-variant">RM</div>
                            <div class="w-9 h-9 rounded-full bg-surface-variant border-2 border-surface-container-low flex items-center justify-center font-label-sm text-label-sm text-on-surface-variant">SC</div>
                            @if ($stats['organizations'] > 3)
                                <div class="w-9 h-9 rounded-full bg-primary-container border-2 border-surface-container-low flex items-center justify-center font-label-sm text-label-sm text-on-primary">+{{ number_format($stats['organizations'] - 3) }}</div>
                            @endif
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">
                            Trusted by <strong class="text-on-surface">{{ number_format($stats['organizations']) }}</strong> organizations
                        </p>
                    </div>
                </div>

                <div class="relative py-lg">
                    <div class="relative w-full max-w-[420px] mx-auto bg-surface border border-outline-variant rounded-xl shadow-2xl p-lg transform rotate-[-2deg] transition-transform duration-500 hover:rotate-0 hover:scale-[1.02]">
                        <div class="flex items-center justify-between mb-lg">
                            <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Acme Institute</span>
                            <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[18px]">check</span>
                            </div>
                        </div>
                        <p class="font-label-sm text-label-sm text-on-surface-variant mb-xs">Certificate of Completion</p>
                        <p class="font-headline-lg text-headline-lg text-on-surface font-bold mb-xs">Priya Raman</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-lg">Advanced Data Analytics &middot; 40 hours</p>
                        <div class="border-t border-outline-variant pt-md flex items-center justify-between">
                            <span class="font-mono text-[11px] text-on-surface-variant">ID PX4-9K21-VERIFY</span>
                            <span class="material-symbols-outlined text-on-surface-variant text-[22px]">qr_code_2</span>
                        </div>
                    </div>

                    <div class="hero-float absolute -top-4 -right-2 md:right-2 bg-surface border border-outline-variant rounded-full shadow-lg pl-2 pr-3 py-1.5 flex items-center gap-xs">
                        <div class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[14px] text-white">verified</span>
                        </div>
                        <span class="font-label-sm text-label-sm text-on-surface">Verified</span>
                    </div>

                    <div class="hero-float hero-float-delayed absolute -bottom-5 -left-4 md:-left-8 bg-surface border border-outline-variant rounded-xl shadow-lg px-md py-sm hidden sm:flex items-center gap-sm">
                        <span class="material-symbols-outlined text-primary text-[22px]">workspace_premium</span>
                        <div>
                            <p class="font-label-md text-label-md text-on-surface font-bold leading-none">{{ number_format($stats['certificates']) }}</p>
                            <p class="font-label-sm text-label-sm text-on-surface-variant leading-none mt-1">issued</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <style>
            .hero-float { animation: hero-float 5s ease-in-out infinite; }
            .hero-float-delayed { animation-delay: 1s; }
            @keyframes hero-float {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-8px); }
            }
            @media (prefers-reduced-motion: reduce) {
                .hero-float { animation: none; }
            }
        </style>

        <section class="py-xl bg-surface border-y border-outline-variant">
            <div class="max-w-[1200px] mx-auto px-margin flex flex-wrap justify-center gap-2xl items-center text-on-surface-variant">
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined text-primary">workspace_premium</span>
                    <span class="font-label-md text-label-md"><strong class="text-on-surface">{{ number_format($stats['certificates']) }}</strong> Certificates Issued</span>
                </div>
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined text-primary">domain</span>
                    <span class="font-label-md text-label-md"><strong class="text-on-surface">{{ number_format($stats['organizations']) }}</strong> Organizations</span>
                </div>
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined text-primary">verified</span>
                    <span class="font-label-md text-label-md"><strong class="text-on-surface">{{ number_format($stats['verifications']) }}</strong> Verifications Run</span>
                </div>
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined text-primary">layers</span>
                    <span class="font-label-md text-label-md"><strong class="text-on-surface">{{ number_format($stats['templates']) }}</strong> Templates Available</span>
                </div>
            </div>
        </section>

        <section class="py-2xl px-margin bg-background" id="features">
            <div class="max-w-[1200px] mx-auto">
                <div class="text-center mb-2xl">
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-sm">Everything you need to issue credentials</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant max-w-[600px] mx-auto">A powerful suite of tools designed for educational institutions, enterprises, and professional bodies.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter fade-up">
                    <div class="bg-surface border border-outline-variant rounded-xl p-lg flex flex-col shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-surface-container flex items-center justify-center text-primary mb-md">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">design_services</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md text-on-surface mb-xs">Visual Builder</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant flex-grow">Design stunning certificates with our drag-and-drop editor. Add logos, signatures, and dynamic variable fields instantly.</p>
                    </div>
                    <div class="bg-surface border border-outline-variant rounded-xl p-lg flex flex-col shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-surface-container flex items-center justify-center text-primary mb-md">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">upload_file</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md text-on-surface mb-xs">Bulk Upload</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant flex-grow">Import thousands of recipients via CSV. Our system automatically maps data to your template for massive scale issuance.</p>
                    </div>
                    <div class="bg-surface border border-outline-variant rounded-xl p-lg flex flex-col shadow-sm hover:shadow-md transition-shadow">
                        <div class="w-12 h-12 rounded-lg bg-surface-container flex items-center justify-center text-primary mb-md">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">qr_code_scanner</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md text-on-surface mb-xs">Instant Verification</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant flex-grow">Every certificate includes a unique, cryptographically secure QR code linking to a hosted verification page.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-2xl px-margin bg-surface-container-low overflow-hidden" id="templates">
            <div class="max-w-[1200px] mx-auto text-center mb-xl">
                <h2 class="font-headline-lg text-headline-lg text-on-surface mb-sm">Start with professional templates</h2>
                <p class="font-body-md text-body-md text-on-surface-variant max-w-[500px] mx-auto">Choose from our library of rigorously designed, premium templates or build your own from scratch.</p>
            </div>

            @if ($featuredTemplates->isNotEmpty())
                <div class="template-carousel hide-scrollbar fade-up px-margin max-w-[1200px] mx-auto">
                    @foreach ($featuredTemplates as $template)
                        <div class="min-w-[300px] md:min-w-[400px] aspect-[4/3] rounded-xl bg-surface border border-outline-variant shadow-sm snap-center relative overflow-hidden group">
                            @if ($template->thumbnail_path)
                                <img src="{{ Storage::url($template->thumbnail_path) }}" alt="{{ $template->name }}" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
                            @else
                                <div class="absolute inset-0 flex flex-col items-center justify-center gap-xs bg-gradient-to-br from-surface-container to-surface-variant p-lg text-center">
                                    <span class="material-symbols-outlined text-primary text-[36px]">workspace_premium</span>
                                    <p class="font-headline-md text-headline-md text-on-surface">{{ $template->name }}</p>
                                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">{{ $template->category }}</p>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="max-w-[1200px] mx-auto grid grid-cols-2 md:grid-cols-4 gap-md px-margin fade-up">
                    @foreach (['Achievement' => 'workspace_premium', 'Completion' => 'school', 'Corporate' => 'domain', 'Recognition' => 'military_tech'] as $category => $icon)
                        <div class="aspect-[4/3] rounded-xl border border-dashed border-outline-variant flex flex-col items-center justify-center gap-xs text-center bg-surface">
                            <span class="material-symbols-outlined text-on-surface-variant text-[32px]">{{ $icon }}</span>
                            <p class="font-label-md text-label-md text-on-surface-variant">{{ $category }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="text-center mt-xl">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-lg py-sm bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-md hover:bg-primary-container transition-colors">
                    Browse Template Library
                </a>
            </div>
        </section>

        <section class="py-2xl px-margin bg-surface border-t border-outline-variant">
            <div class="max-w-[1200px] mx-auto">
                <div class="text-center mb-xl">
                    <h2 class="font-headline-lg text-headline-lg text-on-surface">How it works</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-xl relative fade-up">
                    <div class="hidden md:block absolute top-6 left-1/6 right-1/6 h-0.5 bg-outline-variant z-0"></div>
                    <div class="flex flex-col items-center text-center relative z-10">
                        <div class="w-12 h-12 rounded-full bg-primary text-on-primary font-headline-md text-headline-md flex items-center justify-center mb-md border-4 border-surface shadow-sm">1</div>
                        <h4 class="font-headline-md text-headline-md text-on-surface mb-xs">Design</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Create your template using our visual builder or import existing designs.</p>
                    </div>
                    <div class="flex flex-col items-center text-center relative z-10">
                        <div class="w-12 h-12 rounded-full bg-surface-variant text-on-surface font-headline-md text-headline-md flex items-center justify-center mb-md border-4 border-surface shadow-sm">2</div>
                        <h4 class="font-headline-md text-headline-md text-on-surface mb-xs">Connect Data</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Upload your recipient list via CSV or integrate directly with your LMS.</p>
                    </div>
                    <div class="flex flex-col items-center text-center relative z-10">
                        <div class="w-12 h-12 rounded-full bg-surface-variant text-on-surface font-headline-md text-headline-md flex items-center justify-center mb-md border-4 border-surface shadow-sm">3</div>
                        <h4 class="font-headline-md text-headline-md text-on-surface mb-xs">Issue &amp; Verify</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Send certificates instantly via email with permanent verification links.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-2xl px-margin bg-surface-container-low border-t border-outline-variant">
            <div class="max-w-[1200px] mx-auto">
                <div class="text-center mb-2xl">
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-sm">Trusted by teams who issue every week</h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter fade-up">
                    <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-sm flex flex-col gap-md">
                        <span class="material-symbols-outlined text-primary text-[28px]">format_quote</span>
                        <p class="font-body-md text-body-md text-on-surface flex-grow">We used to spend two days at the end of every cohort generating and emailing PDFs by hand. Now it's one CSV upload before lunch.</p>
                        <div>
                            <p class="font-label-md text-label-md text-on-surface font-semibold">Ananya Krishnan</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">Program Lead, Acme Institute</p>
                        </div>
                    </div>
                    <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-sm flex flex-col gap-md">
                        <span class="material-symbols-outlined text-primary text-[28px]">format_quote</span>
                        <p class="font-body-md text-body-md text-on-surface flex-grow">Our alumni verify their certificates on LinkedIn constantly. Having a real public verification page instead of a static PDF changed how seriously people take our program.</p>
                        <div>
                            <p class="font-label-md text-label-md text-on-surface font-semibold">Rahul Menon</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">Director, TestOrg Academy</p>
                        </div>
                    </div>
                    <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-sm flex flex-col gap-md">
                        <span class="material-symbols-outlined text-primary text-[28px]">format_quote</span>
                        <p class="font-body-md text-body-md text-on-surface flex-grow">Bulk issuing 800 certificates for our annual conference used to be an all-hands scramble. It ran unattended overnight and every recipient had their email by morning.</p>
                        <div>
                            <p class="font-label-md text-label-md text-on-surface font-semibold">Sarah Chen</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant">Operations, Obiikriationz</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if ($pricingPlans->isNotEmpty())
            <section class="py-2xl px-margin bg-background border-t border-outline-variant" id="pricing">
                <div class="max-w-[1200px] mx-auto">
                    <div class="text-center mb-2xl">
                        <h2 class="font-headline-lg text-headline-lg text-on-surface mb-sm">Simple, transparent pricing</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant max-w-[500px] mx-auto">Start free. Upgrade only when you actually need more volume.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter fade-up items-stretch">
                        @foreach ($pricingPlans as $plan)
                            @php $isFree = $plan->isFree(); @endphp
                            <div @class([
                                'rounded-xl p-lg flex flex-col shadow-sm',
                                'bg-surface border border-outline-variant' => $isFree,
                                'bg-surface border-2 border-primary-container shadow-[0_8px_24px_rgba(217,39,39,0.15)]' => ! $isFree,
                            ])>
                                <h3 class="font-headline-md text-headline-md text-on-surface mb-xs">{{ $plan->name }}</h3>
                                <p class="font-body-sm text-body-sm text-on-surface-variant mb-md">{{ $plan->description }}</p>
                                <div class="mb-md">
                                    @if ($isFree)
                                        <span class="text-3xl font-black text-on-surface tracking-tighter">Free</span>
                                    @else
                                        <span class="text-3xl font-black text-on-surface tracking-tighter">{{ $currency === 'INR' ? '₹' : '$' }}{{ number_format($plan->priceFor('monthly', $currency), 0) }}</span>
                                        <span class="font-body-sm text-body-sm text-on-surface-variant">/mo</span>
                                    @endif
                                </div>
                                <ul class="space-y-xs mb-lg flex-grow">
                                    <li class="flex items-center gap-xs font-body-sm text-body-sm text-on-surface-variant">
                                        <span class="material-symbols-outlined text-primary text-[18px]">check</span>
                                        {{ number_format($plan->certificates_per_month) }} certificates / month
                                    </li>
                                    <li class="flex items-center gap-xs font-body-sm text-body-sm text-on-surface-variant">
                                        <span class="material-symbols-outlined text-primary text-[18px]">check</span>
                                        {{ number_format($plan->users_per_month) }} recipients / month
                                    </li>
                                </ul>
                                <a href="{{ route('register') }}" class="w-full text-center py-2 px-4 rounded-lg font-label-md text-label-md {{ $isFree ? 'border border-outline-variant text-on-surface bg-surface hover:bg-surface-variant' : 'bg-primary-container text-on-primary hover:bg-primary' }} transition-colors">
                                    Get Started
                                </a>
                            </div>
                        @endforeach
                    </div>
                    <div class="text-center mt-xl">
                        <a href="{{ route('pricing') }}" class="font-label-md text-label-md text-primary hover:underline">Compare all plans &rarr;</a>
                    </div>
                </div>
            </section>
        @endif

        <section class="py-2xl px-margin bg-surface-container-low border-t border-outline-variant">
            <div class="max-w-3xl mx-auto">
                <h2 class="font-headline-lg text-headline-lg text-on-surface text-center mb-xl">Frequently asked questions</h2>
                <div class="space-y-sm fade-up">
                    <div class="border border-outline-variant rounded-lg bg-surface overflow-hidden">
                        <button type="button" data-faq-toggle class="w-full text-left p-md font-headline-md text-headline-md text-on-surface flex justify-between items-center hover:bg-surface-container-low transition-colors focus:outline-none">
                            Do I need a credit card to try Proudify?
                            <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-200">expand_more</span>
                        </button>
                        <div class="hidden p-md pt-0 font-body-md text-body-md text-on-surface-variant border-t border-outline-variant bg-surface-container-lowest">
                            No. The Free plan is available the moment your account is approved, with no payment details required.
                        </div>
                    </div>
                    <div class="border border-outline-variant rounded-lg bg-surface overflow-hidden">
                        <button type="button" data-faq-toggle class="w-full text-left p-md font-headline-md text-headline-md text-on-surface flex justify-between items-center hover:bg-surface-container-low transition-colors focus:outline-none">
                            How many certificates can I issue in one bulk upload?
                            <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-200">expand_more</span>
                        </button>
                        <div class="hidden p-md pt-0 font-body-md text-body-md text-on-surface-variant border-t border-outline-variant bg-surface-container-lowest">
                            Up to a few thousand recipients per CSV, processed as a background batch — you'll see live progress and get notified the moment it's done.
                        </div>
                    </div>
                    <div class="border border-outline-variant rounded-lg bg-surface overflow-hidden">
                        <button type="button" data-faq-toggle class="w-full text-left p-md font-headline-md text-headline-md text-on-surface flex justify-between items-center hover:bg-surface-container-low transition-colors focus:outline-none">
                            How does certificate verification actually work?
                            <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-200">expand_more</span>
                        </button>
                        <div class="hidden p-md pt-0 font-body-md text-body-md text-on-surface-variant border-t border-outline-variant bg-surface-container-lowest">
                            Every certificate carries a signed verification code and QR code linking to a public page. If a certificate is edited or revoked outside the app, the signature no longer matches and verification fails.
                        </div>
                    </div>
                    <div class="border border-outline-variant rounded-lg bg-surface overflow-hidden">
                        <button type="button" data-faq-toggle class="w-full text-left p-md font-headline-md text-headline-md text-on-surface flex justify-between items-center hover:bg-surface-container-low transition-colors focus:outline-none">
                            Can I use my own certificate design?
                            <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-200">expand_more</span>
                        </button>
                        <div class="hidden p-md pt-0 font-body-md text-body-md text-on-surface-variant border-t border-outline-variant bg-surface-container-lowest">
                            Yes — build one from scratch in the visual editor, or start from any template in the library and customize logos, colors, and fields.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-2xl px-margin bg-background">
            <div class="max-w-[800px] mx-auto bg-surface border border-outline-variant rounded-xl p-2xl text-center shadow-lg relative overflow-hidden fade-up">
                <div class="relative z-10">
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-sm">Ready to elevate your credentials?</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant mb-lg max-w-[400px] mx-auto">Start issuing verifiable certificates today. Simple, transparent pricing tailored for organizations of all sizes.</p>
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-xl py-sm bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-md hover:bg-primary-container transition-colors">
                        Start Free Trial
                    </a>
                </div>
                <div class="absolute bottom-0 left-0 w-full h-1/2 bg-gradient-to-t from-surface-container-low to-transparent z-0"></div>
            </div>
        </section>
    </main>

    <x-site-footer />
</body>
</html>

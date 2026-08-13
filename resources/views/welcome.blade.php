@php use Illuminate\Support\Facades\Storage; @endphp
<x-layouts.app title="Verifiable Credentials" :full-width="true" :show-status="false">

    {{-- Masthead --}}
    <section class="relative bg-white border-b border-outline-variant overflow-hidden">
        <div aria-hidden="true" class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-48 right-[-12%] w-[560px] h-[560px] rounded-full bg-primary-fixed/45 blur-[120px]"></div>
            <div class="absolute bottom-[-30%] left-[-10%] w-[420px] h-[420px] rounded-full bg-secondary-fixed/40 blur-[100px]"></div>
            <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-primary/35 to-transparent"></div>
        </div>

        <div class="relative max-w-[1080px] mx-auto px-margin pt-2xl pb-xl lg:pt-[88px] lg:pb-[64px]">
            <div class="landing-fade flex items-center gap-md mb-lg">
                <p class="font-headline-xl text-[28px] md:text-[32px] leading-none text-primary tracking-tight">{{ config('app.name') }}</p>
                <span class="hidden sm:block h-px w-16 bg-primary/25"></span>
                <p class="hidden sm:block font-label-sm text-label-sm text-on-surface-variant tracking-wider uppercase">Certificate platform</p>
            </div>

            <h1 class="landing-fade landing-fade-1 font-headline-xl text-[42px] leading-[1.05] sm:text-[52px] md:text-[64px] md:leading-[1.02] text-on-surface tracking-tight font-bold text-balance max-w-3xl">
                Issue certificates your recipients can prove.
            </h1>

            <div class="landing-fade landing-fade-2 mt-xl grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_auto] gap-xl md:items-end border-t border-on-surface/[0.08] pt-xl">
                <p class="font-body-md text-[17px] leading-relaxed text-on-surface-variant max-w-md">
                    Build a template, upload your list, and send verifiable credentials — with a public page anyone can open in seconds.
                </p>
                <div class="flex flex-wrap gap-sm">
                    <a href="{{ route('register') }}" class="btn-primary h-12 px-xl inline-flex items-center gap-xs">
                        Create free account
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </a>
                    <a href="#features" class="h-12 px-xl inline-flex items-center justify-center rounded-lg border border-outline-variant bg-white text-on-surface font-label-md text-label-md hover:border-outline hover:bg-surface-container-low transition-colors">
                        How it works
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- Stats --}}
    <section class="bg-surface-container-low border-b border-outline-variant">
        <div class="max-w-[1080px] mx-auto px-margin py-xl grid grid-cols-2 lg:grid-cols-4">
            @foreach ([
                [$stats['certificates'], 'Certificates issued'],
                [$stats['organizations'], 'Organizations'],
                [$stats['verifications'], 'Verifications run'],
                [$stats['templates'], 'Active templates'],
            ] as $i => [$n, $label])
                <div @class([
                    'py-sm lg:py-0 lg:px-lg',
                    'border-l-2 border-primary/30 pl-md lg:pl-lg' => $i === 0,
                    'border-l border-outline-variant pl-md lg:pl-lg' => $i > 0,
                    'mt-md lg:mt-0' => $i >= 2,
                ])>
                    <p class="font-headline-xl text-[28px] md:text-[34px] leading-none text-on-surface tabular-nums tracking-tight">{{ number_format($n) }}</p>
                    <p class="mt-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-[0.12em]">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 01 Design --}}
    <section id="features" class="bg-white border-b border-outline-variant scroll-mt-24" x-data="{ shown: false }" x-intersect.once.threshold.14="shown = true">
        <div class="max-w-[1080px] mx-auto px-margin py-2xl lg:py-[80px] grid grid-cols-1 lg:grid-cols-12 gap-xl lg:gap-2xl items-center">
            <div class="lg:col-span-5 transition-all duration-700 ease-out" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                <p class="font-label-sm text-label-sm text-primary tracking-[0.2em] uppercase mb-md">01 — Design</p>
                <h2 class="font-headline-xl text-[32px] md:text-[40px] leading-[1.12] text-on-surface tracking-tight font-bold">
                    You control every layout decision.
                </h2>
                <p class="mt-lg font-body-md text-[16px] leading-relaxed text-on-surface-variant max-w-md">
                    Open the Visual Builder and shape the certificate yourself — then publish so every issue uses the same design.
                </p>
            </div>
            <div class="lg:col-span-7 transition-all duration-700 ease-out delay-100" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                <div class="rounded-2xl border border-outline-variant bg-white p-lg md:p-xl shadow-[0_28px_70px_-40px_rgba(26,31,33,0.4)]">
                    <div class="flex items-center gap-sm mb-lg pb-md border-b border-outline-variant">
                        <span class="w-10 h-10 rounded-xl bg-primary-fixed text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[22px]">design_services</span>
                        </span>
                        <div>
                            <p class="font-label-md text-label-md font-semibold text-on-surface">What you can do</p>
                            <p class="font-label-sm text-[12px] text-on-surface-variant">In the Visual Builder</p>
                        </div>
                    </div>
                    <ul>
                        @foreach ([
                            ['add', 'Add text, logos, signatures, and QR codes'],
                            ['open_with', 'Move and resize anything on the artboard'],
                            ['upload', 'Upload your brand files and drop them in place'],
                            ['publish', 'Publish — future certificates reuse this layout'],
                        ] as [$icon, $line])
                            <li class="flex items-start gap-md py-md border-t border-outline-variant first:border-0 first:pt-0">
                                <span class="mt-0.5 w-8 h-8 rounded-lg bg-surface-container-low text-primary flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[18px]">{{ $icon }}</span>
                                </span>
                                <span class="font-body-md text-body-md text-on-surface pt-1">{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- 02 Issue --}}
    <section class="bg-floor border-b border-outline-variant" x-data="{ shown: false }" x-intersect.once.threshold.14="shown = true">
        <div class="max-w-[1080px] mx-auto px-margin py-2xl lg:py-[80px] grid grid-cols-1 lg:grid-cols-12 gap-xl lg:gap-2xl items-center">
            <div class="lg:col-span-7 order-2 lg:order-1 transition-all duration-700 ease-out" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                <div class="rounded-2xl border border-outline-variant bg-white p-lg md:p-xl shadow-[0_28px_70px_-40px_rgba(26,31,33,0.35)]">
                    <div class="flex items-start justify-between gap-md mb-lg pb-md border-b border-outline-variant">
                        <div>
                            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">What you can do</p>
                            <p class="font-headline-md text-headline-md text-on-surface mt-xs">Bulk issue from a spreadsheet</p>
                        </div>
                        <span class="inline-flex items-center gap-xs rounded-full bg-secondary-container text-on-secondary-container px-sm py-1 font-label-sm text-label-sm shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-secondary animate-pulse"></span>
                            In progress
                        </span>
                    </div>
                    <ul class="space-y-sm mb-lg">
                        @foreach ([
                            'Upload a CSV of recipients',
                            'Map columns to certificate fields',
                            'Send PDFs and emails in one job',
                        ] as $line)
                            <li class="flex items-center gap-sm font-body-sm text-body-sm text-on-surface">
                                <span class="material-symbols-outlined text-secondary text-[18px]">check_circle</span>
                                {{ $line }}
                            </li>
                        @endforeach
                    </ul>
                    <div class="space-y-sm pt-md border-t border-outline-variant">
                        <div class="h-2 rounded-full bg-surface-container overflow-hidden">
                            <div class="h-full rounded-full bg-secondary transition-[width] duration-1000 ease-out" :style="shown ? 'width: 72%' : 'width: 0%'"></div>
                        </div>
                        <div class="flex justify-between font-label-sm text-label-sm text-on-surface-variant">
                            <span>Example: 864 / 1,200 queued</span>
                            <span class="tabular-nums font-semibold text-on-surface">72%</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-5 order-1 lg:order-2 transition-all duration-700 ease-out delay-100" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                <p class="font-label-sm text-label-sm text-primary tracking-[0.2em] uppercase mb-md">02 — Issue</p>
                <h2 class="font-headline-xl text-[32px] md:text-[40px] leading-[1.12] text-on-surface tracking-tight font-bold">
                    You upload the list. We finish the send.
                </h2>
                <p class="mt-lg font-body-md text-[16px] leading-relaxed text-on-surface-variant">
                    Skip manual PDF exports. Connect your cohort file once and let issuance run while you move on.
                </p>
            </div>
        </div>
    </section>

    {{-- 03 Verify --}}
    <section class="bg-white border-b border-outline-variant" x-data="{ shown: false }" x-intersect.once.threshold.14="shown = true">
        <div class="max-w-[1080px] mx-auto px-margin py-2xl lg:py-[80px] grid grid-cols-1 lg:grid-cols-12 gap-xl lg:gap-2xl items-center">
            <div class="lg:col-span-5 transition-all duration-700 ease-out" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                <p class="font-label-sm text-label-sm text-primary tracking-[0.2em] uppercase mb-md">03 — Verify</p>
                <h2 class="font-headline-xl text-[32px] md:text-[40px] leading-[1.12] text-on-surface tracking-tight font-bold">
                    Recipients share a link others can trust.
                </h2>
                <p class="mt-lg font-body-md text-[16px] leading-relaxed text-on-surface-variant max-w-md">
                    Every certificate gets a QR and public page. Employers open it and see if the credential is still valid.
                </p>
                <ul class="mt-lg space-y-sm">
                    @foreach (['Open the verification page', 'Scan the QR on the PDF', 'Confirm recipient and program details'] as $line)
                        <li class="flex items-center gap-sm font-body-sm text-body-sm text-on-surface">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary shrink-0"></span>
                            {{ $line }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="lg:col-span-7 transition-all duration-700 ease-out delay-100" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                <div class="rounded-2xl border border-outline-variant bg-white overflow-hidden shadow-[0_28px_70px_-40px_rgba(26,31,33,0.35)]">
                    <div class="flex items-center gap-sm px-md py-sm border-b border-outline-variant bg-surface-container-low">
                        <span class="flex gap-1.5" aria-hidden="true">
                            <span class="w-2.5 h-2.5 rounded-full bg-outline"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-outline"></span>
                            <span class="w-2.5 h-2.5 rounded-full bg-outline"></span>
                        </span>
                        <p class="ml-sm font-label-sm text-[11px] text-on-surface-variant">Verification page</p>
                    </div>
                    <div class="p-lg md:p-xl grid grid-cols-1 sm:grid-cols-2 gap-xl">
                        <div>
                            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Check result</p>
                            <p class="mt-sm font-headline-md text-headline-md text-on-surface flex items-center gap-xs">
                                <span class="material-symbols-outlined text-emerald-600 text-[22px]" style="font-variation-settings: 'FILL' 1">verified</span>
                                Authentic
                            </p>
                            <p class="mt-sm font-body-sm text-body-sm text-on-surface-variant">This file matches the issued record.</p>
                        </div>
                        <div class="sm:border-l sm:border-outline-variant sm:pl-xl space-y-md">
                            <div>
                                <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Recipient</p>
                                <p class="mt-xs font-label-md text-label-md text-on-surface font-semibold">Priya Raman</p>
                            </div>
                            <div>
                                <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Program</p>
                                <p class="mt-xs font-body-sm text-body-sm text-on-surface">Data Analytics Intensive</p>
                            </div>
                            <p class="font-mono text-[12px] text-on-surface-variant tracking-wide">PFY-9K21-VERIFY</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Templates --}}
    <section id="templates" class="bg-floor border-b border-outline-variant scroll-mt-24" x-data="{ shown: false }" x-intersect.once.threshold.12="shown = true">
        <div class="max-w-[1080px] mx-auto px-margin py-2xl lg:py-[80px]">
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-lg mb-xl border-b border-outline-variant pb-xl transition-all duration-700 ease-out" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                <div class="max-w-xl">
                    <p class="font-label-sm text-label-sm text-primary tracking-[0.2em] uppercase mb-md">04 — Templates</p>
                    <h2 class="font-headline-xl text-[32px] md:text-[40px] leading-[1.12] text-on-surface tracking-tight font-bold">
                        Pick a starting design. Customize it your way.
                    </h2>
                    <p class="mt-md font-body-md text-body-md text-on-surface-variant">Open a library template or begin blank — then publish when it looks right.</p>
                </div>
                <a href="{{ route('register') }}" class="font-label-md text-label-md text-primary hover:underline inline-flex items-center gap-xs shrink-0 group">
                    Get access to the library
                    <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-0.5">arrow_forward</span>
                </a>
            </div>

            @if ($featuredTemplates->isNotEmpty())
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-md transition-all duration-700 ease-out delay-75" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                    @foreach ($featuredTemplates->take(4) as $template)
                        <article class="bg-white border border-outline-variant rounded-xl overflow-hidden transition-[border-color,box-shadow,transform] hover:border-outline hover:shadow-md hover:-translate-y-0.5">
                            <div class="aspect-[1000/707] bg-surface-container-low p-sm">
                                @if ($template->thumbnail_path)
                                    <img src="{{ Storage::url($template->thumbnail_path) }}" alt="{{ $template->name }}" class="w-full h-full object-contain bg-white rounded-sm" loading="lazy">
                                @else
                                    <div class="w-full h-full flex items-center justify-center border border-dashed border-outline-variant rounded-sm">
                                        <span class="material-symbols-outlined text-outline-variant text-[28px]">workspace_premium</span>
                                    </div>
                                @endif
                            </div>
                            <div class="px-sm py-sm border-t border-outline-variant">
                                <p class="font-label-md text-label-md text-on-surface truncate">{{ $template->name }}</p>
                                <p class="font-label-sm text-[11px] text-on-surface-variant mt-0.5 uppercase tracking-wider">{{ $template->category ?? 'General' }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-md">
                    @foreach (['Achievement', 'Completion', 'Corporate', 'Recognition'] as $category)
                        <div class="aspect-[4/3] border border-dashed border-outline-variant rounded-xl flex items-center justify-center bg-white">
                            <p class="font-label-md text-label-md text-on-surface-variant">{{ $category }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- Quote --}}
    <section class="bg-white border-b border-outline-variant">
        <div class="max-w-[760px] mx-auto px-margin py-2xl lg:py-[80px] text-center" x-data="{ shown: false }" x-intersect.once.threshold.15="shown = true">
            <blockquote class="transition-all duration-700 ease-out" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                <p class="font-label-sm text-label-sm text-primary tracking-[0.2em] uppercase mb-lg">From a program lead</p>
                <p class="font-headline-xl text-[26px] md:text-[36px] leading-snug text-on-surface tracking-tight text-balance">
                    “We stopped spending weekend evenings on PDFs. One spreadsheet upload covers the whole cohort.”
                </p>
                <footer class="mt-xl pt-lg border-t border-outline-variant inline-flex flex-col items-center">
                    <p class="font-label-md text-label-md text-on-surface font-semibold">Ananya Krishnan</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">Program Lead, Acme Institute</p>
                </footer>
            </blockquote>
        </div>
    </section>

    {{-- Pricing --}}
    @if ($billing)
        <section id="pricing" class="bg-floor border-b border-outline-variant scroll-mt-24" x-data="{ shown: false }" x-intersect.once.threshold.12="shown = true">
            <div class="max-w-[1080px] mx-auto px-margin py-2xl lg:py-[80px]">
                <div class="max-w-2xl mb-2xl transition-all duration-700 ease-out" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                    <p class="font-label-sm text-label-sm text-primary tracking-[0.2em] uppercase mb-md">05 — Pricing</p>
                    <h2 class="font-headline-xl text-[32px] md:text-[40px] leading-[1.12] text-on-surface tracking-tight font-bold">
                        Pay only for what you issue.
                    </h2>
                    <p class="mt-md font-body-md text-body-md text-on-surface-variant">No subscriptions, no monthly fees. Design templates for free, then pay per certificate when you issue.</p>
                </div>

                <div class="max-w-md border border-outline-variant rounded-2xl overflow-hidden bg-white shadow-sm p-lg md:p-xl transition-all duration-700 ease-out delay-75" :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-6'">
                    <h3 class="font-headline-md text-headline-md text-on-surface">Pay-per-certificate</h3>
                    <p class="mt-xs font-body-sm text-body-sm text-on-surface-variant">Every account starts free — you're only charged when you actually issue.</p>
                    <div class="mt-lg mb-lg">
                        <span class="font-headline-xl text-[36px] leading-none text-on-surface tracking-tight">₹{{ number_format($billing->price_per_certificate_inr, 0) }}</span>
                        <span class="font-body-sm text-body-sm text-on-surface-variant">/ certificate issued</span>
                    </div>
                    <ul class="space-y-sm mb-xl">
                        <li class="flex items-center gap-xs font-body-sm text-body-sm text-on-surface">
                            <span class="material-symbols-outlined text-secondary text-[18px]">check</span>
                            Unlimited templates in the Visual Builder — free
                        </li>
                        <li class="flex items-center gap-xs font-body-sm text-body-sm text-on-surface">
                            <span class="material-symbols-outlined text-secondary text-[18px]">check</span>
                            Verification pages and QR codes included
                        </li>
                        @if ($billing->bulk_discount_percent > 0)
                            <li class="flex items-center gap-xs font-body-sm text-body-sm text-on-surface">
                                <span class="material-symbols-outlined text-secondary text-[18px]">check</span>
                                {{ number_format($billing->bulk_discount_percent, 0) }}% off automatically on orders over {{ number_format($billing->bulk_discount_threshold) }} certificates
                            </li>
                        @endif
                    </ul>
                    <a href="{{ route('register') }}" class="btn-primary h-11 inline-flex items-center justify-center rounded-lg font-label-md text-label-md transition-colors w-full">Create free account</a>
                </div>
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    <section class="bg-white border-b border-outline-variant">
        <div class="max-w-[720px] mx-auto px-margin py-2xl lg:py-[80px]">
            <p class="font-label-sm text-label-sm text-primary tracking-[0.2em] uppercase mb-md">06 — FAQ</p>
            <h2 class="font-headline-xl text-[32px] md:text-[40px] leading-[1.12] text-on-surface tracking-tight font-bold mb-md">
                Common questions before you start
            </h2>
            <p class="font-body-md text-body-md text-on-surface-variant mb-xl">Short answers so you know what you’ll be able to do right away.</p>

            <div class="divide-y divide-outline-variant border-y border-outline-variant">
                @foreach ([
                    ['Can I try without a credit card?', 'Yes. After your account is approved, the Free plan is available with no payment details.'],
                    ['What can I do after I sign up?', 'Create templates in the Visual Builder, issue single or bulk certificates, and share verification links.'],
                    ['How do recipients prove a certificate is real?', 'They open the public verification page or scan the QR. If the file was changed or revoked, the check fails.'],
                    ['Can my team use our own branding?', 'Yes. Upload logos and signatures, place them on the artboard, and publish that design for every future issue.'],
                ] as [$q, $a])
                    <div x-data="{ open: false }" class="py-md">
                        <button type="button" @click="open = !open" class="w-full text-left flex justify-between gap-md items-center group focus:outline-none focus-visible:ring-2 focus-visible:ring-primary rounded">
                            <span class="font-headline-md text-headline-md text-on-surface group-hover:text-primary transition-colors">{{ $q }}</span>
                            <span class="w-8 h-8 rounded-full border border-outline-variant flex items-center justify-center text-on-surface-variant shrink-0 transition-all" :class="open && 'rotate-45 border-primary text-primary bg-primary-fixed/40'">
                                <span class="material-symbols-outlined text-[18px]">add</span>
                            </span>
                        </button>
                        <div x-show="open" x-cloak x-transition.opacity class="mt-sm pr-12 font-body-md text-body-md text-on-surface-variant leading-relaxed">
                            {{ $a }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Close --}}
    <section class="relative bg-on-surface text-white overflow-hidden">
        <div aria-hidden="true" class="absolute inset-0 pointer-events-none">
            <div class="absolute inset-y-0 right-0 w-1/2 bg-gradient-to-l from-primary-container/30 to-transparent"></div>
            <div class="absolute -left-20 bottom-0 w-72 h-72 rounded-full bg-secondary/20 blur-[80px]"></div>
        </div>
        <div class="relative max-w-[1080px] mx-auto px-margin py-2xl lg:py-[80px] flex flex-col md:flex-row md:items-end md:justify-between gap-xl">
            <div class="max-w-lg">
                <p class="font-label-sm text-label-sm text-white/45 tracking-[0.2em] uppercase mb-md">Get started</p>
                <h2 class="font-headline-xl text-[32px] md:text-[44px] leading-[1.08] tracking-tight font-bold">
                    Ready to issue your next cohort?
                </h2>
                <p class="mt-md font-body-md text-body-md text-white/65">
                    Create an account, design a template, and send verifiable certificates today.
                </p>
            </div>
            <div class="flex flex-wrap gap-sm shrink-0">
                <a href="{{ route('register') }}" class="btn-primary h-12 px-xl inline-flex items-center gap-xs">
                    Create free account
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
                <a href="{{ route('contact') }}" class="h-12 px-xl inline-flex items-center justify-center rounded-lg border border-white/25 text-white font-label-md text-label-md hover:bg-white/10 transition-colors">
                    Talk to us
                </a>
            </div>
        </div>
    </section>

    <style>
        html { scroll-behavior: smooth; }
        .landing-fade { animation: landing-rise 750ms cubic-bezier(0.22, 1, 0.36, 1) both; }
        .landing-fade-1 { animation-delay: 90ms; }
        .landing-fade-2 { animation-delay: 160ms; }
        @keyframes landing-rise {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .landing-fade { animation: none; }
        }
    </style>

</x-layouts.app>

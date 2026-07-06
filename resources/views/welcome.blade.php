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
                <a href="{{ route('pricing') }}" class="font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">Pricing</a>
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
        <section class="relative pt-[80px] pb-2xl px-margin overflow-hidden bg-surface-container-low">
            <div class="max-w-[1200px] mx-auto grid grid-cols-1 md:grid-cols-2 gap-2xl items-center relative z-10">
                <div class="flex flex-col gap-xl">
                    <div class="inline-flex items-center gap-xs px-sm py-base bg-surface border border-outline-variant rounded-full w-max">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-widest">Proudify Platform</span>
                    </div>
                    <h1 class="font-headline-xl text-headline-xl md:text-[40px] md:leading-[48px] text-on-surface text-balance font-bold">
                        Issue Professional Certificates in Minutes
                    </h1>
                    <p class="font-body-md text-body-md text-on-surface-variant max-w-[480px]">
                        The trusted platform for verifiable credentials. Design, generate, and distribute secure certificates to thousands of recipients instantly.
                    </p>
                    <div class="flex flex-wrap items-center gap-md pt-xs">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-lg py-sm bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-md hover:bg-primary-container transition-all hover:scale-[1.02]">
                            Start Free Trial
                        </a>
                        <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center px-lg py-sm bg-surface border border-outline text-on-surface font-label-md text-label-md rounded-lg hover:bg-surface-variant transition-colors">
                            Book Demo
                        </a>
                    </div>
                </div>
                <div class="relative w-full aspect-square md:aspect-auto md:h-[500px] rounded-xl overflow-hidden shadow-xl border border-outline-variant bg-surface flex flex-col items-center justify-center gap-lg p-xl">
                    <img src="{{ asset('images/proudify-logo.png') }}" alt="{{ config('app.name') }}" class="w-full max-w-[280px] h-auto object-contain" />
                    <div class="text-center">
                        <p class="font-headline-lg text-headline-lg font-black text-primary">{{ config('app.name') }}</p>
                        <p class="font-body-md text-body-md text-on-surface-variant mt-xs">Certifications, Simplified.</p>
                    </div>
                </div>
            </div>
            <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-primary-fixed rounded-full blur-[120px] opacity-20 -z-0 pointer-events-none transform translate-x-1/3 -translate-y-1/4"></div>
        </section>

        <section class="py-xl bg-surface border-y border-outline-variant">
            <div class="max-w-[1200px] mx-auto px-margin flex flex-wrap justify-center gap-2xl items-center text-on-surface-variant opacity-80">
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined">verified</span>
                    <span class="font-label-md text-label-md">10,000+ Issued</span>
                </div>
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined">security</span>
                    <span class="font-label-md text-label-md">Bank-grade Security</span>
                </div>
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined">api</span>
                    <span class="font-label-md text-label-md">Seamless Integrations</span>
                </div>
                <div class="flex items-center gap-xs">
                    <span class="material-symbols-outlined">public</span>
                    <span class="font-label-md text-label-md">Globally Verifiable</span>
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

        @if (isset($featuredTemplates) && $featuredTemplates->isNotEmpty())
            <section class="py-2xl px-margin bg-surface-container-low overflow-hidden">
                <div class="max-w-[1200px] mx-auto text-center mb-xl">
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-sm">Start with professional templates</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant max-w-[500px] mx-auto">Choose from our library of rigorously designed, premium templates or build your own from scratch.</p>
                </div>
                <div class="template-carousel hide-scrollbar fade-up px-margin max-w-[1200px] mx-auto">
                    @foreach ($featuredTemplates as $template)
                        <div class="min-w-[300px] md:min-w-[400px] aspect-[4/3] rounded-xl bg-surface border border-outline-variant shadow-sm snap-center relative overflow-hidden group">
                            <img src="{{ Storage::url($template->thumbnail_path) }}" alt="{{ $template->name }}" class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
                        </div>
                    @endforeach
                </div>
                <div class="text-center mt-xl">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-lg py-sm bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-md hover:bg-primary-container transition-colors">
                        Browse Template Library
                    </a>
                </div>
            </section>
        @endif

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

        <section class="py-2xl px-margin bg-background" id="pricing">
            <div class="max-w-[800px] mx-auto bg-surface border border-outline-variant rounded-xl p-2xl text-center shadow-lg relative overflow-hidden fade-up">
                <div class="relative z-10">
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-sm">Ready to elevate your credentials?</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant mb-lg max-w-[400px] mx-auto">Start issuing verifiable certificates today. Simple, transparent pricing tailored for organizations of all sizes.</p>
                    <a href="{{ route('pricing') }}" class="inline-flex items-center justify-center px-xl py-sm bg-primary text-on-primary font-label-md text-label-md rounded-lg shadow-md hover:bg-primary-container transition-colors">
                        View Pricing Plans
                    </a>
                </div>
                <div class="absolute bottom-0 left-0 w-full h-1/2 bg-gradient-to-t from-surface-container-low to-transparent z-0"></div>
            </div>
        </section>
    </main>

    <x-site-footer />
</body>
</html>

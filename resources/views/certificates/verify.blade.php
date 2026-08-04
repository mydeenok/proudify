@php
$statusConfig = [
    'valid' => ['label' => 'Verified Authentic', 'icon' => 'verified', 'class' => 'bg-emerald-50 text-emerald-600 border-emerald-200'],
    'revoked' => ['label' => 'Revoked', 'icon' => 'block', 'class' => 'bg-red-50 text-red-600 border-red-200'],
    'expired' => ['label' => 'Expired', 'icon' => 'schedule', 'class' => 'bg-amber-50 text-amber-600 border-amber-200'],
    'not_found' => ['label' => 'Not Found', 'icon' => 'help', 'class' => 'bg-gray-50 text-gray-600 border-gray-200'],
][$status];
@endphp

<x-layouts.app title="Certificate Verification" :show-status="false">
    <div class="w-full max-w-5xl mx-auto">
        @if ($certificate)
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter items-start">
                <div class="lg:col-span-8">
                    <div class="bg-white/95 backdrop-blur-[10px] border border-outline-variant p-sm rounded-xl overflow-hidden shadow-lg shadow-card">
                        @if ($certificate->image_path)
                            <img src="{{ route('certificates.verify.image', ['uuid' => $certificate->uuid, 'code' => $certificate->verification_code]) }}" alt="{{ $certificate->title }}" class="w-full h-auto object-contain rounded-lg border border-outline-variant" />
                        @else
                            <div class="aspect-[1.414/1] bg-surface-container-low flex items-center justify-center rounded-lg border border-outline-variant">
                                <span class="material-symbols-outlined text-[64px] text-on-surface-variant/20">history_edu</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="lg:col-span-4 flex flex-col gap-lg">
                    <div class="bg-white/95 backdrop-blur-[10px] border border-outline-variant p-lg rounded-xl flex flex-col items-center text-center gap-md shadow-card">
                        <div class="inline-flex items-center gap-xs px-sm py-xs rounded-full font-label-md text-label-md border {{ $statusConfig['class'] }} {{ $status === 'valid' ? 'verified-glow' : '' }}">
                            <span class="material-symbols-outlined text-[18px]">{{ $statusConfig['icon'] }}</span>
                            {{ $statusConfig['label'] }}
                        </div>
                        <div class="flex flex-col gap-xs">
                            <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $certificate->title }}</h1>
                            <p class="font-body-md text-body-md text-on-surface-variant">Issued to <span class="font-semibold text-on-background">{{ $certificate->recipient_name }}</span></p>
                        </div>
                    </div>

                    <div class="bg-white/95 backdrop-blur-[10px] border border-outline-variant p-lg rounded-xl flex flex-col gap-md shadow-card">
                        <h2 class="font-headline-md text-headline-md text-on-surface border-b border-outline-variant pb-xs">Details</h2>
                        <div class="grid grid-cols-2 gap-y-md gap-x-sm">
                            <div class="flex flex-col gap-base">
                                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Issuer</span>
                                <span class="font-body-md text-body-md text-on-surface font-medium">{{ $certificate->user->organization_name }}</span>
                            </div>
                            <div class="flex flex-col gap-base">
                                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Date Issued</span>
                                <span class="font-body-md text-body-md text-on-surface font-medium">{{ $certificate->date_of_issue->format('M d, Y') }}</span>
                            </div>
                            <div class="flex flex-col gap-base col-span-2">
                                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Credential ID</span>
                                <div class="flex items-center gap-sm">
                                    <span class="font-body-md text-body-md text-on-surface font-mono bg-surface-container-low px-2 py-1 rounded border border-outline-variant inline-block">{{ $certificate->verification_code }}</span>
                                    <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($certificate->verification_code)).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})" class="p-xs rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary transition-colors" title="Copy ID">
                                        <span class="material-symbols-outlined text-[16px]" x-show="!copied">content_copy</span>
                                        <span class="material-symbols-outlined text-[16px]" x-show="copied" x-cloak>check</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/95 backdrop-blur-[10px] border border-outline-variant p-lg rounded-xl flex flex-col gap-md shadow-card">
                        @if ($status === 'valid' && $certificate->pdf_path)
                            <a href="{{ route('certificates.verify.download', ['uuid' => $certificate->uuid, 'code' => $certificate->verification_code]) }}" class="btn-primary w-full">
                                <span class="material-symbols-outlined text-[18px]">download</span>
                                Download PDF
                            </a>
                        @endif
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($certificate->verify_url) }}" target="_blank" rel="noopener" class="btn-secondary w-full">
                            <span class="material-symbols-outlined text-[18px]">share</span>
                            Share to LinkedIn
                        </a>
                        <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($certificate->verify_url)).then(() => { copied = true; setTimeout(() => copied = false, 2000) }).catch(() => {})" class="btn-secondary w-full">
                            <span class="material-symbols-outlined text-[18px]" x-show="!copied">link</span>
                            <span x-show="!copied">Copy Public Link</span>
                            <span class="material-symbols-outlined text-[16px]" x-show="copied" x-cloak>check</span>
                        </button>
                    </div>

                    @if ($certificate->qr_code_path)
                        <div class="bg-white/95 backdrop-blur-[10px] border border-outline-variant p-md rounded-xl flex items-center gap-md shadow-card">
                            <div class="w-16 h-16 bg-white border border-outline-variant rounded p-1 shrink-0">
                                <img src="{{ route('certificates.verify.qr', ['uuid' => $certificate->uuid, 'code' => $certificate->verification_code]) }}" alt="Verification QR code" class="w-full h-full object-contain" />
                            </div>
                            <div class="flex flex-col">
                                <span class="font-label-md text-label-md text-on-surface font-semibold">Scan to Verify</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">Instant cryptographic verification via mobile device.</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="max-w-md mx-auto bg-white/95 backdrop-blur-[10px] border border-outline-variant p-2xl rounded-xl text-center shadow-card">
                <div class="w-16 h-16 rounded-full bg-gray-50 text-gray-600 border border-gray-200 flex items-center justify-center mx-auto mb-md">
                    <span class="material-symbols-outlined text-[32px]">{{ $statusConfig['icon'] }}</span>
                </div>
                <h1 class="font-headline-xl text-headline-xl text-on-surface mb-sm">{{ $statusConfig['label'] }}</h1>
                <p class="font-body-md text-body-md text-on-surface-variant">We couldn't find a certificate matching this verification link.</p>
                <a href="/" class="btn-primary mt-lg inline-flex">
                    Back to Proudify
                </a>
            </div>
        @endif
    </div>
</x-layouts.app>

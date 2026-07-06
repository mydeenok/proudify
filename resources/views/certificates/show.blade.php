@php
use Illuminate\Support\Facades\Storage;

$initialImageUrl = $certificate->image_path
    ? Storage::url($certificate->image_path).'?v='.$certificate->updated_at->timestamp
    : null;
$initialQrUrl = $certificate->qr_code_path
    ? Storage::url($certificate->qr_code_path).'?v='.$certificate->updated_at->timestamp
    : null;
@endphp

<x-layouts.user-shell title="{{ $certificate->title }}">
    <x-page-header
        :title="$certificate->title"
        :description="'Issued to ' . $certificate->recipient_name . ' on ' . $certificate->date_of_issue->format('d M Y')"
    >
        <x-slot:actions>
            <a href="{{ auth()->user()->isAdmin() ? route('admin.certificates.index') : route('certificates.index') }}" class="btn-secondary">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Back
            </a>
        </x-slot:actions>
    </x-page-header>

    <div
        id="certificate-show"
        class="grid grid-cols-1 lg:grid-cols-12 gap-gutter items-start"
        x-data="{
            imageUrl: @js($initialImageUrl),
            qrUrl: @js($initialQrUrl),
            pdfReady: @js((bool) $certificate->pdf_path),
            ready: @js((bool) $certificate->image_path),
            generationStatus: @js($certificate->image_generation_status ?? 'pending'),
            displayStatus: @js($certificate->displayStatus()),
            verificationCode: @js($certificate->verification_code),
            templateName: @js($certificate->template->name),
            pollTimer: null,
            poll() {
                fetch('{{ route('certificates.status', $certificate) }}', { headers: { Accept: 'application/json' } })
                    .then(response => response.json())
                    .then(data => {
                        this.imageUrl = data.image_url;
                        this.qrUrl = data.qr_url;
                        this.pdfReady = data.pdf_ready;
                        this.ready = data.ready;
                        this.generationStatus = data.image_generation_status;
                        this.displayStatus = data.display_status;
                        this.verificationCode = data.verification_code;
                        this.templateName = data.template_name;

                        if (! this.ready && this.generationStatus !== 'failed') {
                            this.pollTimer = setTimeout(() => this.poll(), 2000);
                        }
                    })
                    .catch(() => {
                        this.pollTimer = setTimeout(() => this.poll(), 4000);
                    });
            },
            async regenerate() {
                const response = await fetch('{{ route('certificates.regenerate', $certificate) }}', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                });

                if (! response.ok) return;

                this.generationStatus = 'processing';
                this.ready = false;
                this.poll();
            },
        }"
        x-init="poll()"
    >
        <div class="lg:col-span-8">
            <div class="glass-panel p-sm rounded-xl overflow-hidden shadow-lg bento-shadow">
                <template x-if="ready && imageUrl">
                    <img :src="imageUrl" alt="{{ $certificate->title }}" class="w-full h-auto object-contain rounded-lg border border-outline-variant" />
                </template>

                <template x-if="!ready && generationStatus !== 'failed'">
                    <div class="aspect-[1.414/1] bg-surface-container-low flex flex-col items-center justify-center gap-sm text-on-surface-variant rounded-lg border border-outline-variant">
                        <span class="material-symbols-outlined text-[48px] animate-spin">progress_activity</span>
                        <p class="font-body-md text-body-md" x-text="pdfReady ? 'Generating preview image…' : 'Generating your certificate…'"></p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant/80">This page updates automatically — no need to refresh.</p>
                    </div>
                </template>

                <template x-if="!ready && generationStatus === 'failed'">
                    <div class="aspect-[1.414/1] bg-surface-container-low flex flex-col items-center justify-center gap-md text-on-surface-variant rounded-lg border border-outline-variant p-lg text-center">
                        <span class="material-symbols-outlined text-[48px] text-red-500">error</span>
                        <p class="font-body-md text-body-md text-on-surface">Preview generation failed.</p>
                        <button type="button" class="btn-primary" @click="regenerate()">
                            <span class="material-symbols-outlined text-[18px]">refresh</span>
                            Retry Generation
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <div class="lg:col-span-4 flex flex-col gap-lg">
            <div class="glass-panel p-lg rounded-xl flex flex-col gap-md bento-shadow">
                <h3 class="font-headline-md text-headline-md text-on-surface">Actions</h3>

                <template x-if="pdfReady">
                    <a href="{{ route('certificates.download', $certificate) }}" class="btn-primary w-full">
                        <span class="material-symbols-outlined text-[18px]">download</span>
                        Download PDF
                    </a>
                </template>

                <a href="{{ $certificate->verify_url }}" target="_blank" class="btn-secondary w-full">
                    <span class="material-symbols-outlined text-[18px]">verified</span>
                    Public Verification
                </a>

                <button type="button" class="btn-secondary w-full" @click="regenerate()" x-show="ready">
                    <span class="material-symbols-outlined text-[18px]">refresh</span>
                    Refresh Preview
                </button>
            </div>

            <div class="glass-panel p-lg rounded-xl flex flex-col gap-md bento-shadow">
                <h2 class="font-headline-md text-headline-md text-on-surface border-b border-outline-variant pb-xs">Details</h2>
                <div class="grid grid-cols-2 gap-y-md gap-x-sm">
                    <div class="flex flex-col gap-base">
                        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Status</span>
                        <span class="font-body-md text-body-md text-on-surface font-medium capitalize" x-text="displayStatus"></span>
                    </div>
                    <div class="flex flex-col gap-base">
                        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Template</span>
                        <span class="font-body-md text-body-md text-on-surface font-medium" x-text="templateName"></span>
                    </div>
                    <div class="flex flex-col gap-base col-span-2">
                        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Credential ID</span>
                        <div class="flex items-center gap-sm">
                            <span class="font-body-md text-body-md text-on-surface font-mono bg-surface-container-low px-2 py-1 rounded border border-outline-variant inline-block" x-text="verificationCode"></span>
                            <button type="button" :data-copy="verificationCode" class="p-xs rounded-lg border border-outline-variant text-on-surface-variant hover:text-primary hover:border-primary transition-colors" title="Copy ID">
                                <span class="material-symbols-outlined text-[16px]">content_copy</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-col gap-base">
                        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Issuer</span>
                        <span class="font-body-md text-body-md text-on-surface font-medium">{{ $certificate->user->organization_name }}</span>
                    </div>
                    <div class="flex flex-col gap-base">
                        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Date Issued</span>
                        <span class="font-body-md text-body-md text-on-surface font-medium">{{ $certificate->date_of_issue->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>

            <template x-if="qrUrl">
                <div class="glass-panel p-md rounded-xl flex items-center gap-md bento-shadow">
                    <div class="w-16 h-16 bg-white border border-outline-variant rounded p-1 shrink-0">
                        <img :src="qrUrl" alt="Verification QR code" class="w-full h-full object-contain" />
                    </div>
                    <div class="flex flex-col">
                        <span class="font-label-md text-label-md text-on-surface font-semibold">Scan to Verify</span>
                        <span class="font-body-sm text-body-sm text-on-surface-variant">Instant cryptographic verification via mobile device.</span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-layouts.user-shell>

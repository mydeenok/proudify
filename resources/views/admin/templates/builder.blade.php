<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $template->name }} — Builder — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/certificate-builder.js'])
</head>
<body class="bg-background h-full flex flex-col overflow-hidden text-on-surface font-body-md">
    <header class="bg-surface border-b border-outline-variant shadow-sm w-full z-50 shrink-0">
        <div class="flex justify-between items-center w-full px-xl h-[64px]">
            <div class="flex items-center gap-md">
                <a href="{{ route('admin.templates.index') }}" class="text-on-surface-variant hover:text-primary">
                    <span class="material-symbols-outlined">arrow_back</span>
                </a>
                <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ $template->name }}</span>
            </div>
            <div class="flex items-center gap-md">
                <span id="builder-status" class="font-body-sm text-body-sm text-on-surface-variant"></span>
                <button id="save-draft-btn" type="button" class="px-md h-10 font-label-md text-label-md text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors">
                    Save Draft
                </button>
                <button id="publish-btn" type="button" class="px-md h-10 bg-primary-container text-on-primary rounded-lg font-label-md text-label-md font-semibold hover:bg-primary transition-colors shadow-sm">
                    Publish Design
                </button>
            </div>
        </div>
    </header>

    @php
        $canvasWidth = $template->orientation === 'portrait' ? 707 : 1000;
        $canvasHeight = $template->orientation === 'portrait' ? 1000 : 707;

        $builderConfig = [
            'canvasJson' => $template->canvas_json,
            'orientation' => $template->orientation,
            'backgroundHtml' => $backgroundDetectionHtml,
            'needsAutoDetect' => $needsAutoDetect,
            'saveUrl' => route('admin.templates.builder.save', $template),
            'publishUrl' => route('admin.templates.builder.publish', $template),
            'csrfToken' => csrf_token(),
        ];
    @endphp
    <main class="flex-1 flex overflow-hidden relative" id="certificate-builder" data-config='@json($builderConfig)'>
        <aside class="absolute left-md top-md bottom-md w-16 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-md z-30 flex flex-col items-center py-md gap-sm">
            <button id="tool-add-text" type="button" data-builder-tool="text" class="builder-tool w-12 h-12 flex flex-col items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors" title="Add Text">
                <span class="material-symbols-outlined">text_fields</span>
            </button>
            <button id="tool-add-image" type="button" data-builder-tool="image" class="builder-tool w-12 h-12 flex flex-col items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors" title="Add Custom Image Field">
                <span class="material-symbols-outlined">image</span>
            </button>
            <button id="tool-add-qr" type="button" data-builder-tool="qr" class="builder-tool w-12 h-12 flex flex-col items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors" title="Add QR Code">
                <span class="material-symbols-outlined">qr_code_2</span>
            </button>
            <button id="tool-add-signature" type="button" data-builder-tool="signature" class="builder-tool w-12 h-12 flex flex-col items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors" title="Add Signature">
                <span class="material-symbols-outlined">draw</span>
            </button>
            <div class="w-8 h-px bg-outline-variant"></div>
            <button id="tool-delete" type="button" class="w-12 h-12 flex flex-col items-center justify-center text-error hover:bg-error/10 rounded-lg transition-colors" title="Delete Selected">
                <span class="material-symbols-outlined">delete</span>
            </button>
        </aside>

        <div class="flex-1 canvas-bg flex items-center justify-center overflow-auto p-2xl relative"
             style="background-image: radial-gradient(circle, #e5e7eb 1px, transparent 1px); background-size: 20px 20px;">
            <div class="absolute bottom-md left-1/2 -translate-x-1/2 bg-surface-container-lowest border border-outline-variant rounded-full shadow-sm flex items-center px-sm py-xs gap-sm z-30">
                <button id="zoom-out" type="button" class="text-on-surface-variant hover:text-on-surface"><span class="material-symbols-outlined text-sm">remove</span></button>
                <span id="zoom-level" class="font-label-sm text-label-sm text-on-surface-variant min-w-[40px] text-center">100%</span>
                <button id="zoom-in" type="button" class="text-on-surface-variant hover:text-on-surface"><span class="material-symbols-outlined text-sm">add</span></button>
            </div>

            <div id="canvas-wrapper" class="bg-white shadow-xl border border-outline-variant" style="transform-origin: center; position: relative; width: {{ $canvasWidth }}px; height: {{ $canvasHeight }}px;">
                @if ($backgroundDetectionHtml)
                    <iframe
                        id="builder-background-frame"
                        title="Template background"
                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; pointer-events: none;"
                        srcdoc="{{ $backgroundDetectionHtml }}"
                    ></iframe>
                    <div id="builder-background-loading" class="absolute inset-0 bg-white/90 flex items-center justify-center z-10">
                        <x-loading-messages :messages="['Loading fonts…', 'Getting your design ready…', 'Finding editable fields…']" />
                    </div>
                @endif
                <canvas id="builder-canvas" style="position: relative;"></canvas>
            </div>
        </div>

        <aside class="absolute right-md top-md w-64 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-md z-30 p-md">
            <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-md">Bound Fields</h3>
            <select id="bound-field-select" class="h-10 w-full rounded-lg border border-outline-variant bg-surface px-sm font-body-sm text-body-sm text-on-surface mb-lg">
                <option value="">+ Insert field…</option>
                <option value="recipient_name">Recipient Name</option>
                <option value="title">Certificate Title</option>
                <option value="description">Description</option>
                <option value="date_of_issue">Issue Date</option>
                <option value="date_of_expiry">Expiry Date</option>
                <option value="organization_name">Organization Name</option>
                <option value="verification_code">Verification Code</option>
            </select>

            <button id="add-custom-text-btn" type="button" class="w-full h-10 mb-lg flex items-center justify-center gap-xs font-label-md text-label-md text-primary border border-dashed border-primary rounded-lg hover:bg-primary-fixed transition-colors">
                <span class="material-symbols-outlined text-base">add</span>
                Custom text field
            </button>

            <div id="property-panel" class="hidden space-y-md">
                <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide">Text Properties</h3>
                <div>
                    <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Font Size</label>
                    <input id="prop-font-size" type="number" min="8" max="120" class="h-9 w-full rounded-lg border border-outline-variant px-sm font-body-sm text-body-sm">
                </div>
                <div>
                    <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Color</label>
                    <input id="prop-color" type="color" class="h-9 w-full rounded-lg border border-outline-variant">
                </div>
                <div>
                    <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Alignment</label>
                    <select id="prop-align" class="h-9 w-full rounded-lg border border-outline-variant px-sm font-body-sm text-body-sm">
                        <option value="left">Left</option>
                        <option value="center">Center</option>
                        <option value="right">Right</option>
                    </select>
                </div>
            </div>
        </aside>
    </main>
</body>
</html>

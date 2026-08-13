<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $template->name }} — Builder — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/certificate-builder.js'])
    <style>
        /* Fabric wraps the canvas — keep it filling the artboard. */
        #canvas-wrapper .canvas-container {
            width: 100% !important;
            height: 100% !important;
        }
        #canvas-wrapper .canvas-container canvas {
            display: block;
        }
        #tool-panel {
            transition: width 180ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        .element-card, .field-chip, .tool-btn {
            transition: transform 120ms ease, box-shadow 120ms ease, background-color 150ms ease, border-color 150ms ease, color 150ms ease;
        }
        .element-card:hover {
            transform: translateY(-1px);
        }
        .element-card:active {
            transform: translateY(0);
        }
        #builder-status {
            transition: opacity 200ms ease;
        }
        [data-panel-section].hidden {
            display: none;
        }
        .upload-thumb {
            transition: transform 150ms ease, box-shadow 150ms ease;
        }
        .upload-thumb:hover {
            transform: scale(1.04);
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }
        #canvas-stage {
            transition: transform 150ms ease;
        }
    </style>
</head>
<body class="bg-[#edf0f4] h-full flex flex-col overflow-hidden text-on-surface font-body-md">
    <header class="bg-white border-b border-outline-variant w-full z-50 shrink-0 h-14 flex items-center px-md gap-md">
        <a href="{{ route('admin.templates.index') }}" class="w-9 h-9 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-variant transition-colors" title="Back" data-tooltip="Back">
                    <span class="material-symbols-outlined">arrow_back</span>
                </a>
        <div class="min-w-0 flex-1">
            <p class="font-headline-md text-headline-md font-semibold text-on-surface truncate leading-tight">{{ $template->name }}</p>
            <p class="font-label-sm text-label-sm text-on-surface-variant">
                {{ strtoupper($template->page_format ?? 'a4') }} · {{ ucfirst($template->orientation ?? 'landscape') }}
            </p>
            </div>
        <span id="builder-status" class="font-body-sm text-body-sm text-on-surface-variant hidden sm:inline opacity-0"></span>
        <div class="flex items-center gap-xs">
            <button id="undo-btn" type="button" class="w-9 h-9 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors disabled:opacity-30" title="Undo (Ctrl+Z)" data-tooltip="Undo (Ctrl+Z)" disabled>
                <span class="material-symbols-outlined text-[20px]">undo</span>
                </button>
            <button id="redo-btn" type="button" class="w-9 h-9 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors disabled:opacity-30" title="Redo (Ctrl+Y)" data-tooltip="Redo (Ctrl+Y)" disabled>
                <span class="material-symbols-outlined text-[20px]">redo</span>
                </button>
            <button id="duplicate-btn" type="button" class="w-9 h-9 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors" title="Duplicate (Ctrl+D)" data-tooltip="Duplicate (Ctrl+D)">
                <span class="material-symbols-outlined text-[20px]">content_copy</span>
            </button>
            <button id="lock-btn" type="button" class="w-9 h-9 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors" title="Lock / unlock selected" data-tooltip="Lock / unlock selected">
                <span class="material-symbols-outlined text-[20px]">lock</span>
            </button>
            <div class="hidden md:flex items-center gap-0.5 border-l border-outline-variant pl-xs ml-xs">
                <button type="button" data-object-align="left" class="object-align-btn w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg" title="Align left" data-tooltip="Align left"><span class="material-symbols-outlined text-[18px]">align_horizontal_left</span></button>
                <button type="button" data-object-align="center" class="object-align-btn w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg" title="Align center" data-tooltip="Align center"><span class="material-symbols-outlined text-[18px]">align_horizontal_center</span></button>
                <button type="button" data-object-align="right" class="object-align-btn w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg" title="Align right" data-tooltip="Align right"><span class="material-symbols-outlined text-[18px]">align_horizontal_right</span></button>
                <button type="button" data-object-align="top" class="object-align-btn w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg" title="Align top" data-tooltip="Align top"><span class="material-symbols-outlined text-[18px]">align_vertical_top</span></button>
                <button type="button" data-object-align="middle" class="object-align-btn w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg" title="Align middle" data-tooltip="Align middle"><span class="material-symbols-outlined text-[18px]">align_vertical_center</span></button>
                <button type="button" data-object-align="bottom" class="object-align-btn w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg" title="Align bottom" data-tooltip="Align bottom"><span class="material-symbols-outlined text-[18px]">align_vertical_bottom</span></button>
                <button type="button" data-object-distribute="horizontal" class="object-align-btn w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg" title="Distribute horizontally" data-tooltip="Distribute horizontally"><span class="material-symbols-outlined text-[18px]">horizontal_distribute</span></button>
                <button type="button" data-object-distribute="vertical" class="object-align-btn w-8 h-8 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg" title="Distribute vertically" data-tooltip="Distribute vertically"><span class="material-symbols-outlined text-[18px]">vertical_distribute</span></button>
            </div>
            <button id="safe-area-toggle" type="button" class="w-9 h-9 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors" title="Safe area guide" data-tooltip="Safe area guide">
                <span class="material-symbols-outlined text-[20px]">crop_free</span>
            </button>
            <button id="preview-sample-btn" type="button" class="h-9 px-md font-label-md text-label-md text-on-surface hover:bg-surface-variant rounded-lg transition-colors hidden sm:inline-flex items-center gap-xs" title="Preview with sample data" data-tooltip="Preview with sample data">
                <span class="material-symbols-outlined text-[18px]">visibility</span>
                Preview
            </button>
            <div class="relative hidden md:block">
                <button id="shortcuts-help-btn" type="button" class="w-9 h-9 flex items-center justify-center text-on-surface-variant hover:bg-surface-variant rounded-lg transition-colors" title="Keyboard shortcuts" data-tooltip="Keyboard shortcuts" aria-expanded="false" aria-controls="shortcuts-help-panel">
                    <span class="material-symbols-outlined text-[20px]">keyboard</span>
                </button>
                <div id="shortcuts-help-panel" class="hidden absolute right-0 top-full mt-xs w-64 bg-white border border-outline-variant rounded-xl shadow-xl p-md z-[80]">
                    <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Shortcuts</p>
                    <ul class="space-y-1 font-body-sm text-body-sm text-on-surface">
                        <li class="flex justify-between gap-sm"><span>Select all</span><kbd class="text-on-surface-variant">Ctrl+A</kbd></li>
                        <li class="flex justify-between gap-sm"><span>Duplicate</span><kbd class="text-on-surface-variant">Ctrl+D</kbd></li>
                        <li class="flex justify-between gap-sm"><span>Undo / Redo</span><kbd class="text-on-surface-variant">Ctrl+Z / Y</kbd></li>
                        <li class="flex justify-between gap-sm"><span>Nudge</span><kbd class="text-on-surface-variant">↑ ↓ ← →</kbd></li>
                        <li class="flex justify-between gap-sm"><span>Nudge 10px</span><kbd class="text-on-surface-variant">Shift+Arrow</kbd></li>
                        <li class="flex justify-between gap-sm"><span>Delete</span><kbd class="text-on-surface-variant">Del</kbd></li>
                        <li class="flex justify-between gap-sm"><span>Deselect</span><kbd class="text-on-surface-variant">Esc</kbd></li>
                    </ul>
                </div>
            </div>
        </div>
        <button id="save-draft-btn" type="button" class="h-9 px-md font-label-md text-label-md text-on-surface hover:bg-surface-variant rounded-lg transition-colors">
            Save
        </button>
        <button id="publish-btn" type="button" class="h-9 px-lg bg-primary-container text-on-primary rounded-lg font-label-md text-label-md font-semibold hover:bg-primary hover:shadow-md active:scale-[0.98] transition-all shadow-sm">
            Publish
        </button>
    </header>

    @php
        $canvasWidth = $template->orientation === 'portrait' ? 707 : 1000;
        $canvasHeight = $template->orientation === 'portrait' ? 1000 : 707;

        $pageBackground = $pageBackground ?? null;
        $backgroundPreviewUrl = null;
        if (is_array($pageBackground) && ($pageBackground['type'] ?? null) === 'image' && is_string($pageBackground['value'] ?? null)) {
            $backgroundPreviewUrl = '/storage/'.$pageBackground['value'];
        }

        $builderConfig = [
            'canvasJson' => $template->canvas_json,
            'version' => $template->version,
            'orientation' => $template->orientation,
            'pageFormat' => $template->page_format ?? 'a4',
            'backgroundHtml' => $backgroundDetectionHtml,
            'needsAutoDetect' => $needsAutoDetect,
            'pageBackground' => $pageBackground,
            'pageBackgroundUrl' => $backgroundPreviewUrl,
            'saveUrl' => route('admin.templates.builder.save', $template),
            'publishUrl' => route('admin.templates.builder.publish', $template),
            'assetUrl' => route('admin.templates.builder.asset', $template),
            'previewSampleUrl' => route('admin.templates.builder.preview-sample', $template),
            'libraryListUrl' => route('admin.certificate-assets.index'),
            'libraryUploadUrl' => route('admin.certificate-assets.store'),
            'publishedAt' => optional($template->updated_at)?->toIso8601String(),
            'csrfToken' => csrf_token(),
        ];
    @endphp

    <main class="flex-1 flex min-h-0 overflow-hidden" id="certificate-builder" data-config='@json($builderConfig)'>
        {{-- Icon rail --}}
        <aside class="w-20 shrink-0 bg-white border-r border-outline-variant flex flex-col items-center py-md gap-0.5 overflow-hidden z-20">
            <button type="button" class="rail-tab is-active text-primary w-16 py-2.5 flex flex-col items-center gap-1 rounded-lg hover:bg-surface-variant transition-colors" data-panel="text" title="Text" data-tooltip="Text">
                <span class="material-symbols-outlined text-[21px]" style="font-variation-settings: 'FILL' 1">text_fields</span>
                <span class="text-[10.5px] font-semibold tracking-tight leading-none">Text</span>
            </button>
            <button type="button" class="rail-tab text-on-surface-variant w-16 py-2.5 flex flex-col items-center gap-1 rounded-lg hover:bg-surface-variant transition-colors" data-panel="elements" title="Elements" data-tooltip="Elements">
                <span class="material-symbols-outlined text-[21px]">category</span>
                <span class="text-[10.5px] font-semibold tracking-tight leading-none">Elements</span>
            </button>
            <button type="button" class="rail-tab text-on-surface-variant w-16 py-2.5 flex flex-col items-center gap-1 rounded-lg hover:bg-surface-variant transition-colors" data-panel="uploads" title="Uploads" data-tooltip="Uploads">
                <span class="material-symbols-outlined text-[21px]">upload</span>
                <span class="text-[10.5px] font-semibold tracking-tight leading-none">Uploads</span>
            </button>
            <button type="button" class="rail-tab text-on-surface-variant w-16 py-2.5 flex flex-col items-center gap-1 rounded-lg hover:bg-surface-variant transition-colors" data-panel="background" title="Background" data-tooltip="Background">
                <span class="material-symbols-outlined text-[21px]">wallpaper</span>
                <span class="text-[10.5px] font-semibold tracking-tight leading-none">Background</span>
            </button>
            <button type="button" class="rail-tab text-on-surface-variant w-16 py-2.5 flex flex-col items-center gap-1 rounded-lg hover:bg-surface-variant transition-colors" data-panel="fields" title="Fields" data-tooltip="Fields">
                <span class="material-symbols-outlined text-[21px]">dynamic_form</span>
                <span class="text-[10.5px] font-semibold tracking-tight leading-none">Fields</span>
            </button>
            <button type="button" class="rail-tab text-on-surface-variant w-16 py-2.5 flex flex-col items-center gap-1 rounded-lg hover:bg-surface-variant transition-colors" data-panel="starters" title="Starters" data-tooltip="Starters">
                <span class="material-symbols-outlined text-[21px]">auto_awesome_mosaic</span>
                <span class="text-[10.5px] font-semibold tracking-tight leading-none">Starters</span>
            </button>
            <button type="button" class="rail-tab text-on-surface-variant w-16 py-2.5 flex flex-col items-center gap-1 rounded-lg hover:bg-surface-variant transition-colors" data-panel="library" title="Library" data-tooltip="Library">
                <span class="material-symbols-outlined text-[21px]">photo_library</span>
                <span class="text-[10.5px] font-semibold tracking-tight leading-none">Library</span>
            </button>
            <div class="flex-1"></div>
            <button id="tool-delete" type="button" class="w-16 py-2.5 flex flex-col items-center gap-1 text-error hover:bg-error/10 rounded-lg transition-colors" title="Delete selected" data-tooltip="Delete selected">
                <span class="material-symbols-outlined text-[21px]">delete</span>
                <span class="text-[10.5px] font-semibold tracking-tight leading-none">Delete</span>
            </button>
        </aside>

        {{-- Sliding tool panel --}}
        <div id="tool-panel" class="w-[280px] shrink-0 bg-white border-r border-outline-variant overflow-hidden z-20">
            <div class="w-[280px] h-full overflow-y-auto p-md">

                {{-- Text panel --}}
                <div data-panel-section="text" class="space-y-lg">
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Add text</h3>
                        <div class="space-y-xs">
                            <button id="add-heading-btn" type="button" class="element-card w-full text-left h-14 px-md flex items-center rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="font-headline-md text-headline-md font-semibold text-on-surface">Add a heading</span>
                            </button>
                            <button id="add-body-btn" type="button" class="element-card w-full text-left h-12 px-md flex items-center rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="font-body-md text-body-md text-on-surface">Add body text</span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Certificate fields</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-sm">These fill in automatically per certificate.</p>
                        <div id="field-chip-list" class="flex flex-wrap gap-xs">
                            <button type="button" class="field-chip h-9 px-sm rounded-full border border-outline-variant font-body-sm text-body-sm text-on-surface hover:border-primary hover:bg-primary-fixed/50 hover:text-on-primary-fixed" data-field="recipient_name">Recipient Name</button>
                            <button type="button" class="field-chip h-9 px-sm rounded-full border border-outline-variant font-body-sm text-body-sm text-on-surface hover:border-primary hover:bg-primary-fixed/50 hover:text-on-primary-fixed" data-field="title">Certificate Title</button>
                            <button type="button" class="field-chip h-9 px-sm rounded-full border border-outline-variant font-body-sm text-body-sm text-on-surface hover:border-primary hover:bg-primary-fixed/50 hover:text-on-primary-fixed" data-field="description">Description</button>
                            <button type="button" class="field-chip h-9 px-sm rounded-full border border-outline-variant font-body-sm text-body-sm text-on-surface hover:border-primary hover:bg-primary-fixed/50 hover:text-on-primary-fixed" data-field="date_of_issue">Issue Date</button>
                            <button type="button" class="field-chip h-9 px-sm rounded-full border border-outline-variant font-body-sm text-body-sm text-on-surface hover:border-primary hover:bg-primary-fixed/50 hover:text-on-primary-fixed" data-field="date_of_expiry">Expiry Date</button>
                            <button type="button" class="field-chip h-9 px-sm rounded-full border border-outline-variant font-body-sm text-body-sm text-on-surface hover:border-primary hover:bg-primary-fixed/50 hover:text-on-primary-fixed" data-field="organization_name">Organization Name</button>
                            <button type="button" class="field-chip h-9 px-sm rounded-full border border-outline-variant font-body-sm text-body-sm text-on-surface hover:border-primary hover:bg-primary-fixed/50 hover:text-on-primary-fixed" data-field="verification_code">Verification Code</button>
                        </div>
                        <button id="add-custom-text-btn" type="button" class="mt-sm w-full h-10 flex items-center justify-center gap-xs font-label-md text-label-md text-primary border border-dashed border-primary/50 rounded-lg hover:bg-primary-fixed/40 transition-colors">
                            <span class="material-symbols-outlined text-base">add</span>
                            Custom text field
                        </button>
                    </div>
                </div>

                {{-- Elements panel --}}
                <div data-panel-section="elements" class="hidden space-y-lg">
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Shapes</h3>
                        <div class="grid grid-cols-3 gap-sm">
                            @php
                                $basicShapes = [
                                    ['rect', 'rectangle', 'Rectangle'],
                                    ['circle', 'circle', 'Circle'],
                                    ['triangle', 'change_history', 'Triangle'],
                                    ['diamond', 'diamond', 'Diamond'],
                                    ['pentagon', 'pentagon', 'Pentagon'],
                                    ['hexagon', 'hexagon', 'Hexagon'],
                                    ['star', 'star', 'Star'],
                                    ['arrow', 'arrow_right_alt', 'Arrow'],
                                    ['line', 'horizontal_rule', 'Line'],
                                ];
                            @endphp
                            @foreach ($basicShapes as [$kind, $icon, $label])
                                <button id="tool-add-{{ $kind }}" type="button" data-shape-kind="{{ $kind }}" class="element-card shape-tool-btn aspect-square flex items-center justify-center rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30" title="{{ $label }}" data-tooltip="{{ $label }}">
                                    <span class="material-symbols-outlined text-[26px] text-on-surface-variant">{{ $icon }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Vectors</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-sm">Certificate-style badges &amp; ornaments — fully recolorable.</p>
                        <div class="grid grid-cols-3 gap-sm">
                            @php
                                $vectorShapes = [
                                    ['seal', 'workspace_premium', 'Seal / Badge'],
                                    ['banner', 'sell', 'Ribbon Banner'],
                                    ['heart', 'favorite', 'Heart'],
                                ];
                            @endphp
                            @foreach ($vectorShapes as [$kind, $icon, $label])
                                <button id="tool-add-{{ $kind }}" type="button" data-shape-kind="{{ $kind }}" class="element-card shape-tool-btn aspect-square flex items-center justify-center rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30" title="{{ $label }}" data-tooltip="{{ $label }}">
                                    <span class="material-symbols-outlined text-[26px] text-on-surface-variant">{{ $icon }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">More frames and icons are coming soon.</p>
                </div>

                {{-- Uploads panel --}}
                <div data-panel-section="uploads" class="hidden space-y-lg">
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Your images</h3>
                        <button id="upload-image-btn" type="button" class="w-full h-20 flex flex-col items-center justify-center gap-xs rounded-xl border border-dashed border-primary/50 text-primary hover:bg-primary-fixed/30 transition-colors">
                            <span class="material-symbols-outlined text-[24px]">add_photo_alternate</span>
                            <span class="font-label-md text-label-md">Upload image</span>
                        </button>
                        <input id="upload-image-input" type="file" accept="image/*" class="hidden">
                        <button id="replace-image-btn" type="button" class="mt-sm w-full h-10 font-label-md text-label-md text-on-surface border border-outline-variant rounded-lg hover:bg-surface-variant transition-colors hidden">
                            Replace selected image (keep size)
                        </button>
                        <input id="replace-image-input" type="file" accept="image/*" class="hidden">
                        <div id="uploads-gallery" class="grid grid-cols-3 gap-xs mt-sm"></div>
                    </div>
                </div>

                {{-- Background panel --}}
                <div data-panel-section="background" class="hidden space-y-lg">
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Background color</h3>
                        <div class="grid grid-cols-6 gap-xs mb-sm">
                            @foreach (['#ffffff', '#fff8f0', '#f5f5f4', '#eef2ff', '#fef2f2', '#0f172a'] as $swatch)
                                <button type="button" class="bg-swatch aspect-square rounded-lg border border-outline-variant hover:scale-105 transition-transform" style="background-color: {{ $swatch }}" data-color="{{ $swatch }}" title="{{ $swatch }}" data-tooltip="{{ $swatch }}"></button>
                            @endforeach
                        </div>
                        <div class="flex items-center gap-sm">
                            <input id="bg-color" type="color" value="{{ is_array($pageBackground) && ($pageBackground['type'] ?? '') === 'color' ? ($pageBackground['value'] ?? '#ffffff') : '#ffffff' }}" class="h-10 w-12 rounded-lg border border-outline-variant cursor-pointer shrink-0" title="Custom color" data-tooltip="Custom color">
                            <button id="bg-clear-btn" type="button" class="flex-1 h-10 font-label-sm text-label-sm text-on-surface-variant hover:text-error border border-outline-variant rounded-lg hover:bg-error/5 transition-colors">
                                Reset to white
                            </button>
                        </div>
                    </div>
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Background image</h3>
                        <button id="bg-image-btn" type="button" class="w-full h-10 px-sm font-label-md text-label-md text-primary border border-dashed border-primary/50 rounded-lg hover:bg-primary-fixed/30 transition-colors">
                            Upload image
                        </button>
                        <input id="bg-image-input" type="file" accept="image/*" class="hidden">
                        <p id="bg-status" class="font-body-sm text-body-sm text-on-surface-variant mt-xs truncate opacity-0"></p>
                    </div>
                </div>

                {{-- Fields panel --}}
                <div data-panel-section="fields" class="hidden space-y-lg">
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Dynamic fields</h3>
                        <div class="space-y-xs">
                            <button id="tool-add-qr" type="button" class="element-card w-full h-14 px-md flex items-center gap-sm rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="material-symbols-outlined text-[22px] text-on-surface-variant">qr_code_2</span>
                                <span class="font-body-md text-body-md text-on-surface">QR Code</span>
                            </button>
                            <button id="tool-add-signature" type="button" class="element-card w-full h-14 px-md flex items-center gap-sm rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="material-symbols-outlined text-[22px] text-on-surface-variant">draw</span>
                                <span class="font-body-md text-body-md text-on-surface">Signature</span>
                            </button>
                            <button id="tool-add-company-logo" type="button" class="element-card w-full h-14 px-md flex items-center gap-sm rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="material-symbols-outlined text-[22px] text-on-surface-variant">apartment</span>
                                <span class="font-body-md text-body-md text-on-surface">Company logo</span>
                            </button>
                            <button id="tool-add-image" type="button" class="element-card w-full h-14 px-md flex items-center gap-sm rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="material-symbols-outlined text-[22px] text-on-surface-variant">image</span>
                                <span class="font-body-md text-body-md text-on-surface">Custom image field</span>
                            </button>
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-sm">Company logo uses the issuer&rsquo;s organization logo. Custom image fields let each certificate use a different picture.</p>
                    </div>
                </div>

                {{-- Starters / layout presets --}}
                <div data-panel-section="starters" class="hidden space-y-lg">
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Starter layouts</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-sm">Replace the canvas with a ready scaffold. You can still edit everything after.</p>
                        <div class="space-y-xs">
                            <button type="button" data-preset="award" class="preset-btn element-card w-full text-left px-md py-sm rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="font-body-md text-body-md text-on-surface font-semibold block">Award</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">Title banner, recipient, seal, signature</span>
                            </button>
                            <button type="button" data-preset="course" class="preset-btn element-card w-full text-left px-md py-sm rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="font-body-md text-body-md text-on-surface font-semibold block">Course</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">Clean completion certificate</span>
                            </button>
                            <button type="button" data-preset="internship" class="preset-btn element-card w-full text-left px-md py-sm rounded-xl border border-outline-variant hover:border-primary hover:shadow-sm bg-surface-variant/30">
                                <span class="font-body-md text-body-md text-on-surface font-semibold block">Internship</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">Org logo focus + description</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Shared asset library --}}
                <div data-panel-section="library" class="hidden space-y-lg">
                    <div>
                        <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Shared library</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-sm">Borders, seals &amp; ornaments shared across templates.</p>
                        <button id="library-upload-btn" type="button" class="w-full h-12 flex items-center justify-center gap-xs rounded-xl border border-dashed border-primary/50 text-primary hover:bg-primary-fixed/30 transition-colors font-label-md text-label-md">
                            <span class="material-symbols-outlined text-[20px]">upload</span>
                            Upload to library
                        </button>
                        <input id="library-upload-input" type="file" accept="image/*" class="hidden">
                        <div id="library-gallery" class="grid grid-cols-3 gap-xs mt-sm"></div>
                    </div>
                </div>

            </div>
            </div>

        {{-- Center workspace --}}
        <section id="builder-workspace" class="flex-1 min-w-0 relative overflow-auto flex items-center justify-center p-xl"
             style="background-color:#d8dde5; background-image: radial-gradient(circle, rgba(0,0,0,0.08) 1px, transparent 1px); background-size: 16px 16px;">
            <div id="canvas-stage" class="relative shrink-0" style="transform-origin: center center;">
                <div id="canvas-wrapper" class="bg-white shadow-[0_8px_40px_rgba(0,0,0,0.18)] relative ring-1 ring-black/10"
                     style="width: {{ $canvasWidth }}px; height: {{ $canvasHeight }}px;">
                @if ($backgroundDetectionHtml)
                    <iframe
                        id="builder-background-frame"
                        title="Template background" data-tooltip="Template background"
                        style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; pointer-events: none;"
                        srcdoc="{{ $backgroundDetectionHtml }}"
                    ></iframe>
                    <div id="builder-background-loading" class="absolute inset-0 bg-white/90 flex items-center justify-center z-10">
                        <x-loading-messages :messages="['Loading fonts…', 'Getting your design ready…', 'Finding editable fields…']" />
                    </div>
                @endif
                    <canvas id="builder-canvas"></canvas>
                    <div id="empty-hint" class="pointer-events-none absolute inset-0 flex items-center justify-center z-[1]">
                        <div class="pointer-events-auto max-w-sm text-center px-lg py-md rounded-2xl bg-white/90 border border-outline-variant shadow-sm space-y-sm">
                            <p class="font-body-md text-body-md text-on-surface">Start with a quick setup</p>
                            <div class="flex flex-col gap-xs">
                                <button type="button" id="empty-add-title" class="h-10 rounded-lg border border-outline-variant hover:border-primary font-label-md text-label-md text-on-surface">1. Add title</button>
                                <button type="button" id="empty-add-recipient" class="h-10 rounded-lg border border-outline-variant hover:border-primary font-label-md text-label-md text-on-surface">2. Add recipient</button>
                                <button type="button" id="empty-add-logo" class="h-10 rounded-lg border border-outline-variant hover:border-primary font-label-md text-label-md text-on-surface">3. Add company logo</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Starter replace confirm (no browser alert) --}}
            <div id="preset-confirm-modal" class="hidden fixed inset-0 z-[70] bg-black/45 flex items-center justify-center p-lg" role="dialog" aria-modal="true" aria-labelledby="preset-confirm-title">
                <div class="bg-white rounded-2xl border border-outline-variant shadow-2xl w-full max-w-md p-lg space-y-md">
                    <div class="flex items-start gap-sm">
                        <span class="material-symbols-outlined text-primary text-[28px] shrink-0">layers</span>
                        <div class="min-w-0">
                            <h3 id="preset-confirm-title" class="font-headline-md text-headline-md text-on-surface">Replace current design?</h3>
                            <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">
                                Applying <strong id="preset-confirm-name">this starter</strong> will clear the canvas and load a new layout. You can still Undo after.
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-sm justify-end pt-xs">
                        <button type="button" id="preset-confirm-cancel" class="btn-secondary h-10 px-md">Cancel</button>
                        <button type="button" id="preset-confirm-apply" class="btn-primary h-10 px-md">Replace design</button>
                    </div>
                </div>
            </div>

            {{-- Sample preview: right drawer (canvas stays visible) --}}
            <aside id="sample-preview-drawer" class="hidden fixed top-14 right-0 bottom-0 z-[60] w-full max-w-md bg-white border-l border-outline-variant shadow-2xl flex flex-col" aria-label="Sample certificate preview">
                <div class="flex items-center justify-between gap-sm px-md py-sm border-b border-outline-variant shrink-0">
                    <div class="min-w-0">
                        <h3 class="font-label-md text-label-md text-on-surface truncate">Sample preview</h3>
                        <p class="font-body-sm text-body-sm text-on-surface-variant truncate">With placeholder recipient data</p>
                    </div>
                    <div class="flex items-center gap-xs shrink-0">
                        <button type="button" id="sample-preview-refresh" class="w-9 h-9 rounded-lg hover:bg-surface-variant flex items-center justify-center text-on-surface-variant" title="Refresh preview" data-tooltip="Refresh preview">
                            <span class="material-symbols-outlined text-[20px]">refresh</span>
                        </button>
                        <button type="button" id="sample-preview-close" class="w-9 h-9 rounded-lg hover:bg-surface-variant flex items-center justify-center text-on-surface-variant" title="Close" data-tooltip="Close">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-auto p-md bg-[#edf0f4]">
                    <div id="sample-preview-loading" class="hidden py-xl text-center space-y-sm">
                        <span class="material-symbols-outlined text-[32px] text-primary animate-spin">progress_activity</span>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Rendering sample…</p>
                    </div>
                    <p id="sample-preview-error" class="hidden font-body-sm text-body-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-sm"></p>
                    <div class="bg-white rounded-xl border border-outline-variant shadow-sm overflow-hidden">
                        <img id="sample-preview-image" alt="Sample certificate preview" class="hidden w-full h-auto">
                        <iframe id="sample-preview-frame" title="Sample preview" data-tooltip="Sample preview" class="hidden w-full min-h-[480px] border-0 bg-white"></iframe>
                    </div>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-sm">
                        QR is a placeholder. Logos and signature use your admin profile when set. Draft is saved before preview.
                    </p>
                </div>
            </aside>

            {{-- In-app toast (replaces browser alert) --}}
            <div id="builder-toast" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-[80] max-w-md w-[calc(100%-2rem)] px-md py-sm rounded-xl shadow-lg border font-body-sm text-body-sm text-center" role="status" aria-live="polite"></div>

            <div class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-white border border-outline-variant rounded-full shadow-md flex items-center px-sm py-xs gap-xs z-30">
                <button id="zoom-out" type="button" class="w-8 h-8 flex items-center justify-center text-on-surface-variant hover:text-on-surface rounded-full hover:bg-surface-variant transition-colors"><span class="material-symbols-outlined text-[18px]">remove</span></button>
                <button id="zoom-fit" type="button" class="font-label-sm text-label-sm text-on-surface-variant min-w-[52px] text-center hover:text-on-surface px-xs" title="Fit to screen" data-tooltip="Fit to screen">100%</button>
                <button id="zoom-in" type="button" class="w-8 h-8 flex items-center justify-center text-on-surface-variant hover:text-on-surface rounded-full hover:bg-surface-variant transition-colors"><span class="material-symbols-outlined text-[18px]">add</span></button>
            </div>
        </section>

        {{-- Right panel: properties + layers --}}
        <aside class="w-72 shrink-0 bg-white border-l border-outline-variant flex flex-col z-20 overflow-hidden">
            <div class="p-md overflow-y-auto flex-1 min-h-0">
                <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Properties</h3>

                <div id="property-empty" class="font-body-sm text-body-sm text-on-surface-variant/70 border border-dashed border-outline-variant rounded-xl p-md text-center">
                    Select an element on the canvas to edit its style.
        </div>

                <div id="property-panel" class="hidden space-y-md">
                    <div id="text-props" class="hidden space-y-md">
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Font</label>
                            <select id="prop-font-family" class="h-9 w-full rounded-lg border border-outline-variant px-sm font-body-sm text-body-sm">
                                <optgroup label="Sans-serif">
                                    <option value="Inter">Inter</option>
                                    <option value="Noto Sans">Noto Sans</option>
                                    <option value="Carlito">Carlito (Calibri-style)</option>
                                    <option value="Montserrat">Montserrat</option>
                                </optgroup>
                                <optgroup label="Serif / Fancy">
                                    <option value="Playfair Display">Playfair Display</option>
                                    <option value="Cinzel">Cinzel (Engraved)</option>
                                    <option value="Cormorant Garamond">Cormorant Garamond</option>
                                    <option value="Lora">Lora</option>
                                </optgroup>
                                <optgroup label="Signature / Script">
                                    <option value="Dancing Script">Dancing Script</option>
                                    <option value="Great Vibes">Great Vibes</option>
                                    <option value="Pacifico">Pacifico</option>
                                </optgroup>
                                <optgroup label="Tamil">
                                    <option value="Noto Sans Tamil">Noto Sans Tamil</option>
                                </optgroup>
            </select>
                        </div>
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
                            <div class="flex gap-xs">
                                <button type="button" data-align="left" class="align-btn flex-1 h-9 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors"><span class="material-symbols-outlined text-[18px]">format_align_left</span></button>
                                <button type="button" data-align="center" class="align-btn flex-1 h-9 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors"><span class="material-symbols-outlined text-[18px]">format_align_center</span></button>
                                <button type="button" data-align="right" class="align-btn flex-1 h-9 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors"><span class="material-symbols-outlined text-[18px]">format_align_right</span></button>
                            </div>
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Style</label>
                            <div class="flex gap-xs">
                                <button id="prop-bold" type="button" class="style-btn flex-1 h-9 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors" title="Bold" data-tooltip="Bold"><span class="material-symbols-outlined text-[18px]">format_bold</span></button>
                                <button id="prop-italic" type="button" class="style-btn flex-1 h-9 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors" title="Italic" data-tooltip="Italic"><span class="material-symbols-outlined text-[18px]">format_italic</span></button>
                                <button id="prop-underline" type="button" class="style-btn flex-1 h-9 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors" title="Underline" data-tooltip="Underline"><span class="material-symbols-outlined text-[18px]">format_underlined</span></button>
                                <button id="prop-strikethrough" type="button" class="style-btn flex-1 h-9 flex items-center justify-center rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors" title="Strikethrough" data-tooltip="Strikethrough"><span class="material-symbols-outlined text-[18px]">format_strikethrough</span></button>
                            </div>
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Change Case</label>
                            <div class="flex gap-xs">
                                <button id="prop-case-title" type="button" class="flex-1 h-9 rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors font-body-sm text-body-sm" title="Title Case" data-tooltip="Title Case">Aa</button>
                                <button id="prop-case-upper" type="button" class="flex-1 h-9 rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors font-body-sm text-body-sm" title="UPPERCASE" data-tooltip="UPPERCASE">AA</button>
                                <button id="prop-case-lower" type="button" class="flex-1 h-9 rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors font-body-sm text-body-sm" title="lowercase" data-tooltip="lowercase">aa</button>
                            </div>
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Letter Spacing</label>
                            <input id="prop-letter-spacing" type="range" min="-20" max="400" step="10" class="w-full accent-primary">
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Line Height</label>
                            <input id="prop-line-height" type="range" min="0.8" max="2.5" step="0.05" class="w-full accent-primary">
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Opacity</label>
                            <input id="prop-text-opacity" type="range" min="10" max="100" step="5" class="w-full accent-primary">
                        </div>
                        <div class="border-t border-outline-variant pt-md space-y-sm">
                            <h4 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide">Effects</h4>

                            <div class="grid grid-cols-2 gap-1.5" role="group" aria-label="Text effects">
                                <button id="prop-shadow-toggle" type="button" class="effect-chip style-btn" title="Drop shadow" data-tooltip="Drop shadow" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">blur_on</span>
                                    <span>Shadow</span>
                                </button>
                                <button id="prop-outline-toggle" type="button" class="effect-chip style-btn" title="Text outline" data-tooltip="Text outline" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">border_color</span>
                                    <span>Outline</span>
                                </button>
                                <button id="prop-highlight-toggle" type="button" class="effect-chip style-btn" title="Text highlight" data-tooltip="Text highlight" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">ink_highlighter</span>
                                    <span>Highlight</span>
                                </button>
                                <button id="prop-gradient-toggle" type="button" class="effect-chip style-btn" title="Gradient fill" data-tooltip="Gradient fill" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">gradient</span>
                                    <span>Gradient</span>
                                </button>
                                <button id="prop-autofit-toggle" type="button" class="effect-chip style-btn col-span-2" title="Shrink font automatically if long text overflows this box" data-tooltip="Shrink font automatically if long text overflows this box" aria-pressed="false">
                                    <span class="material-symbols-outlined" aria-hidden="true">fit_screen</span>
                                    <span>Auto-fit to box</span>
                                </button>
                            </div>

                            <div id="shadow-controls" class="effect-options hidden space-y-xs">
                                <div class="grid grid-cols-2 gap-xs items-end">
                                    <div>
                                        <label for="prop-shadow-color">Color</label>
                                        <input id="prop-shadow-color" type="color" class="h-8 w-full rounded-md border border-outline-variant bg-white" title="Shadow color" data-tooltip="Shadow color">
                                    </div>
                                    <div>
                                        <label for="prop-shadow-blur">Blur</label>
                                        <input id="prop-shadow-blur" type="range" min="0" max="20" step="1" class="w-full accent-primary" title="Shadow blur" data-tooltip="Shadow blur">
                                    </div>
                                </div>
                            </div>

                            <div id="outline-controls" class="effect-options hidden space-y-xs">
                                <div class="grid grid-cols-2 gap-xs items-end">
                                    <div>
                                        <label for="prop-outline-color">Color</label>
                                        <input id="prop-outline-color" type="color" class="h-8 w-full rounded-md border border-outline-variant bg-white" title="Outline color" data-tooltip="Outline color">
                                    </div>
                                    <div>
                                        <label for="prop-outline-width">Width</label>
                                        <input id="prop-outline-width" type="range" min="1" max="10" step="1" class="w-full accent-primary" title="Outline width" data-tooltip="Outline width">
                                    </div>
                                </div>
                            </div>

                            <div id="highlight-controls" class="effect-options hidden">
                                <label for="prop-highlight-color">Highlight color</label>
                                <input id="prop-highlight-color" type="color" class="h-8 w-full rounded-md border border-outline-variant bg-white" title="Highlight color" data-tooltip="Highlight color">
                            </div>

                            <div id="gradient-controls" class="effect-options hidden">
                                <div class="grid grid-cols-2 gap-xs">
                                    <div>
                                        <label for="prop-gradient-from">From</label>
                                        <input id="prop-gradient-from" type="color" class="h-8 w-full rounded-md border border-outline-variant bg-white" title="From color" data-tooltip="From color">
                                    </div>
                                    <div>
                                        <label for="prop-gradient-to">To</label>
                                        <input id="prop-gradient-to" type="color" class="h-8 w-full rounded-md border border-outline-variant bg-white" title="To color" data-tooltip="To color">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="shape-props" class="hidden space-y-md">
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Fill</label>
                            <input id="prop-fill" type="color" class="h-9 w-full rounded-lg border border-outline-variant">
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Stroke</label>
                            <input id="prop-stroke" type="color" class="h-9 w-full rounded-lg border border-outline-variant">
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Stroke Width</label>
                            <input id="prop-stroke-width" type="number" min="0" max="40" class="h-9 w-full rounded-lg border border-outline-variant px-sm font-body-sm text-body-sm">
                        </div>
                        <div id="corner-radius-row" class="hidden">
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Corner Radius</label>
                            <input id="prop-corner-radius" type="range" min="0" max="60" step="2" class="w-full accent-primary">
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Opacity</label>
                            <input id="prop-shape-opacity" type="range" min="10" max="100" step="5" class="w-full accent-primary">
                        </div>
                        <div>
                            <label class="font-body-sm text-body-sm text-on-surface-variant block mb-xs">Flip</label>
                            <div class="flex gap-xs">
                                <button id="prop-flip-h" type="button" class="style-btn flex-1 h-9 flex items-center justify-center gap-xs rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors" title="Flip horizontal" data-tooltip="Flip horizontal"><span class="material-symbols-outlined text-[18px]">flip</span><span class="font-label-sm text-label-sm">H</span></button>
                                <button id="prop-flip-v" type="button" class="style-btn flex-1 h-9 flex items-center justify-center gap-xs rounded-lg border border-outline-variant hover:bg-surface-variant transition-colors" title="Flip vertical" data-tooltip="Flip vertical"><span class="material-symbols-outlined text-[18px] rotate-90">flip</span><span class="font-label-sm text-label-sm">V</span></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-md border-t border-outline-variant max-h-56 flex flex-col min-h-0 shrink-0">
                <h3 class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wide mb-sm">Layers</h3>
                <ul id="layers-list" class="space-y-xs overflow-y-auto flex-1"></ul>
            </div>
        </aside>
    </main>
</body>
</html>

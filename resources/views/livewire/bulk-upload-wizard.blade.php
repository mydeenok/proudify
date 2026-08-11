@php
    use Illuminate\Support\Facades\Storage;

    $fields = [
        'title' => 'Certificate Title',
        'recipient_name' => 'Recipient Name',
        'recipient_email' => 'Recipient Email',
        'description' => 'Description (optional)',
        'date_of_issue' => 'Issue Date',
        'date_of_expiry' => 'Expiry Date (optional)',
    ];
    $required = ['title', 'recipient_name', 'recipient_email', 'date_of_issue'];
@endphp

<div @class([
    'max-w-3xl mx-auto' => ! ($mode === 'admin' && $step === 'setup'),
])>
    @if ($step !== 'setup')
        <div class="mb-xl text-center">
            <h1 class="font-headline-xl text-headline-xl text-on-background mb-xs">Bulk Issue Certificates</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">
                @if ($step === 'template')
                    Upload a CSV file to issue multiple certificates at once. Follow the 4-step process below.
                @elseif ($step === 'upload')
                    Using template: <span class="font-semibold text-on-surface">{{ $template?->name }}</span>
                @elseif ($step === 'map')
                    Match your spreadsheet columns to certificate fields.
                @else
                    Review the summary before issuing.
                @endif
            </p>
        </div>

        <x-bulk-upload-stepper :current="$stepperCurrent" />
    @endif

    {{-- Step: Select Template --}}
    @if ($step === 'template')
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-gutter" wire:loading.class="opacity-60">
            @forelse ($templates as $tpl)
                <button
                    type="button"
                    wire:click="selectTemplate({{ $tpl->id }})"
                    wire:key="template-{{ $tpl->id }}"
                    class="group relative card-surface shadow-card-sm overflow-hidden hover:shadow-card transition-shadow duration-300 flex flex-col h-[280px] text-left"
                >
                    <div class="relative flex-1 bg-surface-container-low overflow-hidden">
                        @if ($tpl->thumbnail_path)
                            <img src="{{ Storage::url($tpl->thumbnail_path) }}" alt="{{ $tpl->name }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <span class="material-symbols-outlined text-on-surface-variant text-[48px]">workspace_premium</span>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-on-background/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-sm">
                            <span class="btn-primary pointer-events-none">
                                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                Select Template
                            </span>
                        </div>
                    </div>
                    <div class="p-md bg-surface border-t border-outline-variant">
                        <p class="font-label-md text-label-md text-on-surface font-semibold truncate">{{ $tpl->name }}</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $tpl->category ?? 'General' }}</p>
                    </div>
                </button>
            @empty
                <p class="col-span-full text-center py-2xl font-body-md text-body-md text-on-surface-variant">
                    No templates available yet.
                </p>
            @endforelse
        </div>
    @endif

    {{-- Step: Tenant upload --}}
    @if ($step === 'upload')
        <div class="card-surface p-lg shadow-card-sm mb-xl" wire:loading.class="opacity-60">
            <div class="flex justify-between items-center mb-md">
                <h2 class="font-headline-md text-headline-md text-on-surface">Upload CSV Data</h2>
                <a href="{{ asset('samples/bulk-upload-sample.csv') }}" download class="flex items-center gap-xs text-primary font-label-sm text-label-sm hover:underline">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Download Sample CSV
                </a>
            </div>

            <label
                class="block border-2 border-dashed border-primary-container rounded-lg p-2xl flex flex-col items-center justify-center text-center cursor-pointer hover:bg-surface-container-low transition-colors group"
                x-data="{ dragging: false }"
                :class="dragging ? 'bg-surface-container-low' : 'bg-surface-container-lowest'"
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="dragging = false; if ($event.dataTransfer.files.length) $wire.upload('file', $event.dataTransfer.files[0])"
            >
                <input type="file" wire:model="file" accept=".csv,.xlsx,.xls" class="hidden" x-ref="bulkFileInput">
                <div class="w-16 h-16 bg-primary-fixed text-on-primary-fixed rounded-full flex items-center justify-center mb-md group-hover:scale-110 transition-transform" @click="$refs.bulkFileInput.click()">
                    <span class="material-symbols-outlined text-[32px]">upload_file</span>
                </div>
                <h3 class="font-headline-md text-headline-md text-on-surface mb-xs" @click="$refs.bulkFileInput.click()">
                    Drag &amp; drop CSV or <span class="text-primary underline">click to browse</span>
                </h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant max-w-sm">Supported formats: .csv, .xlsx. Maximum file size 10MB. First row must be column headers.</p>
                <p class="font-label-sm text-label-sm text-on-surface mt-md">
                    {{ $file?->getClientOriginalName() }}
                </p>
                <div wire:loading wire:target="file" class="font-body-sm text-body-sm text-on-surface-variant mt-sm">Uploading…</div>
            </label>
            <x-input-error :messages="$errors->get('file')" />
            <x-input-error :messages="$errors->get('templateId')" />

            <div class="mt-xl flex justify-between items-center">
                <button type="button" wire:click="backToTemplate" class="btn-secondary">Back</button>
                <button type="button" wire:click="continueToMapping" wire:loading.attr="disabled" class="btn-primary">
                    Continue to Mapping
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </div>
        </div>
    @endif

    {{-- Step: Admin setup (org + template + file) --}}
    @if ($step === 'setup')
        <div class="mb-xl">
            <x-page-header
                title="High-Volume Issuance"
                description="Upload and process large batches of certificates without quota restrictions."
            />
        </div>

        <div
            class="grid grid-cols-1 lg:grid-cols-12 gap-gutter"
            x-data="{ userName: '', templateName: '', fileName: @js($file?->getClientOriginalName() ?? ''), dragging: false }"
            wire:loading.class="opacity-60"
        >
            <div class="lg:col-span-8 flex flex-col gap-gutter">
                <div class="card-surface shadow-card-sm p-lg">
                    <div class="flex items-center gap-sm mb-md">
                        <div class="w-8 h-8 rounded-full bg-primary-container/20 flex items-center justify-center text-primary">
                            <span class="material-symbols-outlined text-[18px]">corporate_fare</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md text-on-surface">Target Organization</h3>
                    </div>
                    <x-input-label for="userId" value="Assign to Organization/User" />
                    <select id="userId" wire:model.live="userId"
                        x-on:change="userName = $event.target.options[$event.target.selectedIndex].text"
                        class="form-input">
                        <option value="">Search by organization or email…</option>
                        @foreach ($orgUsers as $orgUser)
                            <option value="{{ $orgUser->id }}">{{ $orgUser->organization_name }} ({{ $orgUser->email }})</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('userId')" />
                </div>

                <div class="card-surface shadow-card-sm p-lg">
                    <div class="flex items-center gap-sm mb-md">
                        <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[18px]">design_services</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md text-on-surface">Select Template</h3>
                    </div>
                    <select id="templateId" wire:model.live="templateId"
                        x-on:change="templateName = $event.target.options[$event.target.selectedIndex].text"
                        class="form-input">
                        <option value="">Select a template…</option>
                        @foreach ($templates as $tpl)
                            <option value="{{ $tpl->id }}">{{ $tpl->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('templateId')" />
                </div>

                <div class="card-surface shadow-card-sm p-lg">
                    <div class="flex items-center gap-sm mb-md">
                        <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[18px]">upload_file</span>
                        </div>
                        <h3 class="font-headline-md text-headline-md text-on-surface">Data Source</h3>
                    </div>
                    <label class="block border-2 border-dashed rounded-xl p-xl flex flex-col items-center justify-center text-center hover:bg-surface-container-low transition-colors cursor-pointer group h-64 relative"
                        :class="dragging ? 'border-primary-container bg-surface-container-low' : 'border-outline-variant bg-surface-container-lowest'"
                        @dragover.prevent="dragging = true"
                        @dragleave="dragging = false"
                        @drop.prevent="dragging = false; if ($event.dataTransfer.files.length) { fileName = $event.dataTransfer.files[0].name; $wire.upload('file', $event.dataTransfer.files[0]) }">
                        <input type="file" wire:model="file" accept=".csv,.xlsx,.xls" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                            x-on:change="fileName = $event.target.files[0]?.name ?? ''">
                        <div class="w-16 h-16 rounded-full bg-surface-variant flex items-center justify-center mb-md group-hover:scale-105 transition-transform duration-300">
                            <span class="material-symbols-outlined text-[32px] text-on-surface-variant">csv</span>
                        </div>
                        <h4 class="font-headline-md text-headline-md text-on-surface mb-xs">Drag &amp; Drop CSV or Excel</h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-md max-w-sm" x-text="fileName || 'Supports up to 2,000 rows per batch.'"></p>
                        <span class="btn-secondary h-10 pointer-events-none relative z-0">Browse Files</span>
                    </label>
                    <x-input-error :messages="$errors->get('file')" />
                    <div wire:loading wire:target="file" class="font-body-sm text-body-sm text-on-surface-variant mt-sm">Uploading…</div>
                </div>
            </div>

            <div class="lg:col-span-4 flex flex-col gap-gutter">
                <div class="card-surface shadow-card-sm p-lg sticky top-[104px]">
                    <h3 class="font-headline-md text-headline-md text-on-surface mb-lg">Batch Summary</h3>
                    <div class="space-y-4 mb-xl">
                        <div class="flex justify-between items-center py-2 border-b border-outline-variant/50">
                            <span class="font-body-md text-body-md text-on-surface-variant">Target Org</span>
                            <span class="font-label-md text-label-md text-on-surface truncate max-w-[150px]" x-text="userName || '—'"></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-outline-variant/50">
                            <span class="font-body-md text-body-md text-on-surface-variant">Template</span>
                            <span class="font-label-md text-label-md text-on-surface text-right truncate max-w-[150px]" x-text="templateName || '—'"></span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-outline-variant/50">
                            <span class="font-body-md text-body-md text-on-surface-variant">File Status</span>
                            <span class="inline-flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant bg-surface-variant px-2 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                                <span x-text="fileName ? 'Ready' : 'Waiting'"></span>
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="font-body-md text-body-md text-on-surface-variant">File</span>
                            <span class="font-label-md text-label-md text-on-surface text-right truncate max-w-[150px]" x-text="fileName || '—'"></span>
                        </div>
                    </div>

                    <div class="p-md bg-surface-container-low rounded-lg mb-xl border border-outline-variant/50">
                        <div class="flex gap-sm">
                            <span class="material-symbols-outlined text-on-surface-variant text-[20px]">info</span>
                            <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                                Quota limits are suspended for admin-issued batches. Column mapping and row review happen on the next screen.
                            </p>
                        </div>
                    </div>

                    <button type="button" wire:click="adminUpload" wire:loading.attr="disabled" class="btn-primary w-full">
                        <span class="material-symbols-outlined">rocket_launch</span>
                        Continue to Mapping
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Step: Map columns --}}
    @if ($step === 'map')
        <form wire:submit="saveMapping" class="card-surface p-lg shadow-card-sm space-y-md" wire:loading.class="opacity-60">
            @foreach ($fields as $field => $label)
                <div wire:key="map-field-{{ $field }}">
                    <x-input-label :for="$field" :value="$label" />
                    <select id="{{ $field }}" wire:model="mapping.{{ $field }}" class="form-input"
                        @if (in_array($field, $required, true)) required @endif>
                        @if (! in_array($field, $required, true) || $mapping[$field] === '' || $mapping[$field] === null)
                            <option value="">{{ in_array($field, $required, true) ? '— Select a column —' : '— None —' }}</option>
                        @endif
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}">{{ $header ?: 'Column '.($index + 1) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('mapping.'.$field)" />
                </div>
            @endforeach

            <x-input-error :messages="$errors->get('file')" />

            <div class="pt-md flex justify-between items-center border-t border-outline-variant">
                <button type="button" wire:click="backFromMap" class="btn-secondary">Back</button>
                <button type="submit" class="btn-primary">
                    Continue to Review
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </div>
        </form>
    @endif

    {{-- Step: Review --}}
    @if ($step === 'review' && $batch)
        <div class="card-surface p-lg shadow-card-sm mb-lg" wire:loading.class="opacity-60">
            <h2 class="font-headline-md text-headline-md text-on-surface mb-md">Batch Summary</h2>
            <dl class="space-y-2">
                <div class="flex justify-between py-2 border-b border-outline-variant/50">
                    <dt class="font-body-md text-body-md text-on-surface-variant">Template</dt>
                    <dd class="font-label-md text-label-md text-on-surface">{{ $batch->template->name }}</dd>
                </div>
                <div class="flex justify-between py-2 border-b border-outline-variant/50">
                    <dt class="font-body-md text-body-md text-on-surface-variant">Total Rows</dt>
                    <dd class="font-label-md text-label-md text-on-surface">{{ $batch->total_rows }}</dd>
                </div>
                <div class="flex justify-between py-2 border-b border-outline-variant/50">
                    <dt class="font-body-md text-body-md text-on-surface-variant">Ready to Issue</dt>
                    <dd class="font-label-md text-label-md text-emerald-700">{{ $pendingCount }}</dd>
                </div>
                @if ($skippedCount > 0)
                    <div class="flex justify-between py-2 border-b border-outline-variant/50">
                        <dt class="font-body-md text-body-md text-on-surface-variant">Skipped (duplicates)</dt>
                        <dd class="font-label-md text-label-md text-amber-600">{{ $skippedCount }}</dd>
                    </div>
                @endif
                @if ($failedCount > 0)
                    <div class="flex justify-between py-2">
                        <dt class="font-body-md text-body-md text-on-surface-variant">Failed Validation</dt>
                        <dd class="font-label-md text-label-md text-error">{{ $failedCount }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($price)
            <div class="card-surface p-lg shadow-card-sm mb-lg">
                <h2 class="font-headline-md text-headline-md text-on-surface mb-md">Price</h2>
                <dl class="space-y-2">
                    <div class="flex justify-between py-2 border-b border-outline-variant/50">
                        <dt class="font-body-md text-body-md text-on-surface-variant">{{ number_format($price['unit_price'], 2) }} &times; {{ $price['quantity'] }}</dt>
                        <dd class="font-label-md text-label-md text-on-surface">₹{{ number_format($price['subtotal'], 2) }}</dd>
                    </div>
                    @if ($price['discount_amount'] > 0)
                        <div class="flex justify-between py-2 border-b border-outline-variant/50 text-emerald-700">
                            <dt class="font-body-md text-body-md">Bulk discount ({{ number_format($price['discount_percent'], 0) }}%)</dt>
                            <dd class="font-label-md text-label-md">&minus;₹{{ number_format($price['discount_amount'], 2) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between py-2">
                        <dt class="font-label-md text-label-md text-on-surface font-semibold">Total due</dt>
                        <dd class="font-headline-md text-headline-md text-on-surface font-bold">₹{{ number_format($price['total'], 2) }}</dd>
                    </div>
                </dl>
            </div>
        @endif

        @if ($pendingCount === 0)
            <div class="bg-error/10 text-error rounded-lg px-md py-sm font-body-sm text-body-sm mb-lg">
                No rows are ready to issue. Check your file and column mapping.
            </div>
        @endif
        <x-input-error :messages="$errors->get('batch')" />

        <div class="flex justify-between items-center mt-lg">
            <button type="button" wire:click="backToMap" class="btn-secondary">Back</button>
            <button
                type="button"
                wire:click="confirm"
                wire:loading.attr="disabled"
                @disabled($pendingCount === 0)
                class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <span class="material-symbols-outlined text-[18px]">{{ $price ? 'lock' : 'rocket_launch' }}</span>
                @if ($price)
                    Pay ₹{{ number_format($price['total'], 2) }} &amp; Issue {{ $pendingCount }} Certificates
                @else
                    Issue {{ $pendingCount }} Certificates
                @endif
            </button>
        </div>
    @endif
</div>

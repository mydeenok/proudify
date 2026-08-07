<x-layouts.contextual-shell
    title="Create New Certificate"
    subtitle="Single Issuance"
    :back-url="route('templates.index')"
>
    @php
        $draft = $draftPayload ?? [];
        $fieldHighlightsJson = $fieldHighlights ?? [];
    @endphp

    <x-slot:actions>
        <button type="button" class="btn-secondary h-10 px-md py-xs text-sm" x-data @click="$dispatch('open-preview-modal')">
            <span class="material-symbols-outlined text-[18px]">visibility</span>
            Preview Certificate
        </button>
        <button
            type="button"
            class="btn-secondary h-10 px-md py-xs text-sm"
            x-data
            @click="$dispatch('save-draft-now')"
            title="Save draft"
        >
            <span class="material-symbols-outlined text-[18px]">save</span>
            Save Draft
        </button>
    </x-slot:actions>
    <div
        class="max-w-[1600px] mx-auto w-full p-margin flex flex-col lg:flex-row gap-margin items-start"
        x-data="certificatePreview(
            @js((string) $template->id),
            @js($previewMode ?? 'html'),
            {
                draftSaveUrl: @js($draftSaveUrl ?? route('certificates.drafts.save')),
                draftDeleteUrl: @js($draftDeleteUrl ?? route('certificates.drafts.destroy')),
                fieldHighlights: @js($fieldHighlightsJson),
                hasDraft: @js((bool) ($hasDraft ?? false)),
                templateNeedsLogo: @js((bool) ($templateNeedsLogo ?? false)),
                templateNeedsSignature: @js((bool) ($templateNeedsSignature ?? false)),
                profileHasSignature: @js((bool) ($profileHasSignature ?? false)),
                profileHasLogo: @js((bool) ($profileHasLogo ?? false)),
            }
        )"
        @save-draft-now.window="saveDraftNow()"
        @open-preview-modal.window="openPreviewModal()"
    >
        <div class="w-full lg:w-5/12 flex flex-col gap-md lg:sticky lg:top-margin lg:self-start lg:max-h-[calc(100vh-6rem)] lg:overflow-y-auto">
            {{-- Progress: Draft → Preview → Issue --}}
            <div class="flex items-center gap-xs flex-wrap" aria-label="Issuance progress">
                <span
                    class="inline-flex items-center gap-1 h-8 px-sm rounded-full font-label-sm text-label-sm border"
                    :class="stepDraft ? 'bg-primary-fixed/50 border-primary text-primary' : 'bg-surface border-outline-variant text-on-surface-variant'"
                >
                    <span class="material-symbols-outlined text-[16px]" x-text="stepDraft ? 'check_circle' : 'edit_note'"></span>
                    Draft
                </span>
                <span class="text-on-surface-variant material-symbols-outlined text-[16px]">chevron_right</span>
                <span
                    class="inline-flex items-center gap-1 h-8 px-sm rounded-full font-label-sm text-label-sm border"
                    :class="stepPreview ? 'bg-primary-fixed/50 border-primary text-primary' : 'bg-surface border-outline-variant text-on-surface-variant'"
                >
                    <span class="material-symbols-outlined text-[16px]" x-text="stepPreview ? 'check_circle' : 'visibility'"></span>
                    Preview
                </span>
                <span class="text-on-surface-variant material-symbols-outlined text-[16px]">chevron_right</span>
                <span
                    class="inline-flex items-center gap-1 h-8 px-sm rounded-full font-label-sm text-label-sm border"
                    :class="stepIssue ? 'bg-primary-fixed/50 border-primary text-primary' : 'bg-surface border-outline-variant text-on-surface-variant'"
                >
                    <span class="material-symbols-outlined text-[16px]">send</span>
                    Issue
                </span>
                <span class="font-body-sm text-body-sm text-on-surface-variant ml-auto" x-show="draftStatus" x-text="draftStatus"></span>
            </div>

            <div class="rounded-xl border border-primary/25 bg-primary-fixed/20 px-md py-sm flex gap-sm items-start">
                <span class="material-symbols-outlined text-primary text-[20px] shrink-0 mt-0.5">lock</span>
                <p class="font-body-sm text-body-sm text-on-surface">
                    This design comes from the admin template. You can edit text and photos only — layout, fonts, and decoration stay locked.
                </p>
            </div>

            @if ($missingSignature ?? false)
                <div class="rounded-xl border border-amber-400/60 bg-amber-50 px-md py-sm flex gap-sm items-start">
                    <span class="material-symbols-outlined text-amber-700 text-[20px] shrink-0">warning</span>
                    <p class="font-body-sm text-body-sm text-on-surface">
                        Your profile is missing a signature used by this template.
                        <a href="{{ route('profile.edit') }}" class="text-primary underline font-medium">Add it in Profile</a>
                        before issuing.
                    </p>
                </div>
            @endif

            @if ($missingLogo ?? false)
                <div class="rounded-xl border border-amber-400/60 bg-amber-50 px-md py-sm flex gap-sm items-start">
                    <span class="material-symbols-outlined text-amber-700 text-[20px] shrink-0">warning</span>
                    <p class="font-body-sm text-body-sm text-on-surface">
                        Your profile has no organization logo, and this template expects one.
                        <a href="{{ route('profile.edit') }}" class="text-primary underline font-medium">Upload a logo</a>
                        before issuing.
                    </p>
                </div>
            @endif

            <form
                id="certificate-create-form"
                method="POST"
                action="{{ route('certificates.store') }}"
                enctype="multipart/form-data"
                class="card-surface p-lg shadow-card-sm space-y-md"
                x-on:submit.prevent="openChecklist($event)"
                data-no-loading-state
            >
                @csrf
                <input type="hidden" id="certificate_template_id" name="template_id" value="{{ $template->id }}">

                <h2 class="font-headline-md text-headline-md text-on-surface mb-lg">Certificate Details</h2>

                <div>
                    <x-input-label for="template_display" value="Design Template" />
                    <input id="template_display" type="text" readonly value="{{ $template->name }}" class="form-input bg-surface-container-low text-on-surface-variant cursor-not-allowed" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                    <div>
                        <x-input-label for="recipient_name" value="Recipient Name" />
                        <x-text-input
                            id="recipient_name"
                            name="recipient_name"
                            type="text"
                            required
                            placeholder="e.g. Jane Doe"
                            :value="old('recipient_name', $draft['recipient_name'] ?? '')"
                            data-binding="recipient_name"
                            x-ref="recipient_name"
                            @input="onFieldInput()"
                            @focus="setHighlight('recipient_name')"
                            @blur="clearHighlight()"
                        />
                        <x-input-error :messages="$errors->get('recipient_name')" />
                    </div>
                    <div>
                        <x-input-label for="recipient_email" value="Recipient Email" />
                        <x-text-input
                            id="recipient_email"
                            name="recipient_email"
                            type="email"
                            required
                            placeholder="jane@example.com"
                            :value="old('recipient_email', $draft['recipient_email'] ?? '')"
                            x-ref="recipient_email"
                            @input="onFieldInput()"
                        />
                        <x-input-error :messages="$errors->get('recipient_email')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="title" value="Certificate Title" />
                    <x-text-input
                        id="title"
                        name="title"
                        type="text"
                        required
                        placeholder="e.g. Certificate of Completion"
                        :value="old('title', $draft['title'] ?? '')"
                        data-binding="title"
                        x-ref="title"
                        @input="onFieldInput()"
                        @focus="setHighlight('title')"
                        @blur="clearHighlight()"
                    />
                    <x-input-error :messages="$errors->get('title')" />
                </div>

                <div>
                    <x-input-label for="description" value="Description / Subtitle" />
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        maxlength="500"
                        class="form-input h-auto py-sm min-h-[88px]"
                        placeholder="Enter custom text for the certificate body…"
                        data-binding="description"
                        x-ref="description"
                        @input="onFieldInput()"
                        @focus="setHighlight('description')"
                        @blur="clearHighlight()"
                    >{{ old('description', $draft['description'] ?? '') }}</textarea>
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                    <div>
                        <x-input-label for="date_of_issue" value="Issue Date" />
                        <x-text-input
                            id="date_of_issue"
                            name="date_of_issue"
                            type="date"
                            required
                            :value="old('date_of_issue', $draft['date_of_issue'] ?? now()->toDateString())"
                            data-binding="date_of_issue"
                            x-ref="date_of_issue"
                            @input="onFieldInput()"
                            @focus="setHighlight('date_of_issue')"
                            @blur="clearHighlight()"
                        />
                        <x-input-error :messages="$errors->get('date_of_issue')" />
                    </div>
                    <div>
                        <x-input-label for="date_of_expiry" value="Expiry Date (optional)" />
                        <x-text-input
                            id="date_of_expiry"
                            name="date_of_expiry"
                            type="date"
                            :value="old('date_of_expiry', $draft['date_of_expiry'] ?? '')"
                            data-binding="date_of_expiry"
                            x-ref="date_of_expiry"
                            @input="onFieldInput()"
                            @focus="setHighlight('date_of_expiry')"
                            @blur="clearHighlight()"
                        />
                        <x-input-error :messages="$errors->get('date_of_expiry')" />
                    </div>
                </div>

                @if (count($customFields))
                    <div class="pt-md border-t border-outline-variant space-y-md">
                        <h2 class="font-headline-md text-headline-md text-on-surface">Template Fields</h2>

                        @foreach ($customFields as $field)
                            @php
                                $customFieldOldValue = old('custom_fields.'.$field['key'], data_get($draft, 'custom_fields.'.$field['key']));
                                $customFieldErrors = $errors->get(($field['type'] === 'image' ? 'custom_image_fields.' : 'custom_fields.').$field['key']);
                            @endphp
                            <div>
                                <x-input-label for="custom-field-{{ $field['key'] }}" :value="$field['label']" />

                                @if ($field['type'] === 'image')
                                    <input
                                        id="custom-field-{{ $field['key'] }}"
                                        type="file"
                                        name="custom_image_fields[{{ $field['key'] }}]"
                                        accept="image/*"
                                        data-custom-image-field
                                        data-binding="{{ $field['key'] }}"
                                        {{ $field['required'] ? 'required' : '' }}
                                        class="form-input py-xs"
                                        @focus="setHighlight(@js($field['key']))"
                                        @blur="clearHighlight()"
                                        @change="markPreviewStale()"
                                    />
                                @else
                                    <x-text-input
                                        id="custom-field-{{ $field['key'] }}"
                                        type="text"
                                        name="custom_fields[{{ $field['key'] }}]"
                                        data-custom-text-field="{{ $field['key'] }}"
                                        data-binding="{{ $field['key'] }}"
                                        :value="$customFieldOldValue"
                                        :required="$field['required']"
                                        @input="onFieldInput()"
                                        @focus="setHighlight(@js($field['key']))"
                                        @blur="clearHighlight()"
                                    />
                                @endif
                                <x-input-error :messages="$customFieldErrors" />
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="pt-md border-t border-outline-variant flex gap-sm">
                    <a href="{{ route('templates.index') }}" class="btn-secondary flex-1">Cancel</a>
                    <x-primary-button type="submit" class="btn-primary flex-1" x-bind:disabled="submitting">
                        <span x-show="!submitting">Issue Certificate</span>
                        <span x-show="!submitting" class="material-symbols-outlined text-[18px]">send</span>
                        <span x-show="submitting" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                        <span x-show="submitting">Issuing certificate…</span>
                    </x-primary-button>
                </div>
            </form>
        </div>

        <div class="w-full lg:w-7/12 flex flex-col min-w-0" id="certificate-live-preview">
            <div class="bg-white/95 backdrop-blur-[10px] border border-outline-variant rounded-xl flex-1 p-md flex flex-col relative min-h-[560px]">
                <div class="flex justify-between items-center mb-md px-xs">
                    <h3 class="font-label-md text-label-md text-on-surface flex items-center gap-xs">
                        <span class="material-symbols-outlined text-primary text-[18px]">visibility</span>
                        Live Preview
                    </h3>
                    <div class="flex items-center gap-md">
                        <x-loading-messages
                            x-show="loading"
                            :messages="['Loading fonts…', 'Rendering your design…']"
                        />
                        <div class="flex gap-xs">
                            <button type="button" @click="zoomIn()" class="w-8 h-8 rounded bg-surface border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[18px]">zoom_in</span>
                            </button>
                            <button type="button" @click="zoomOut()" class="w-8 h-8 rounded bg-surface border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[18px]">zoom_out</span>
                            </button>
                            <span class="w-10 h-8 flex items-center justify-center font-label-sm text-label-sm text-on-surface-variant" x-text="Math.round(zoom * 100) + '%'">100%</span>
                        </div>
                    </div>
                </div>

                <div class="flex-1 bg-surface-container-low rounded-lg border border-outline-variant flex items-center justify-center p-md overflow-hidden relative shadow-inner">
                    <div x-ref="viewport" class="aspect-[1.414/1] w-full max-w-[760px] relative overflow-hidden">
                        <div
                            x-ref="canvas"
                            class="bg-surface shadow-[0_8px_32px_rgba(0,0,0,0.1)] absolute top-0 left-0 border border-[#eaeaea] origin-top-left"
                            style="width: {{ $template->orientation === 'portrait' ? 707 : 1000 }}px; height: {{ $template->orientation === 'portrait' ? 1000 : 707 }}px;"
                        >
                            @if (($previewMode ?? 'html') === 'canvas')
                                <img x-ref="previewImage" title="Certificate preview" alt="Certificate preview" class="w-full h-full object-contain" src="{{ $initialPreviewDataUri }}">
                            @else
                                <iframe x-ref="frame" title="Certificate preview" class="w-full h-full border-0" srcdoc="{{ $initialPreviewHtml }}"></iframe>
                            @endif

                            <template x-if="previewMode === 'canvas' && activeHighlight">
                                <div
                                    class="pointer-events-none absolute border-2 border-primary bg-primary/15 rounded-sm transition-all duration-150"
                                    :style="highlightStyle"
                                ></div>
                            </template>
                        </div>
                    </div>
                </div>

                <p class="font-body-sm text-body-sm text-on-surface-variant mt-md text-center">Preview updates as you type · Template: <strong>{{ $template->name }}</strong></p>
            </div>
        </div>

        {{-- Full-screen preview modal --}}
        <div
            x-show="previewModalOpen"
            x-cloak
            class="fixed inset-0 z-[60] flex flex-col bg-black/55 backdrop-blur-[2px]"
            @keydown.escape.window="if (previewModalOpen) closePreviewModal()"
            role="dialog"
            aria-modal="true"
            aria-label="Certificate preview"
        >
            <div class="flex items-center justify-between gap-md px-lg py-md bg-white border-b border-outline-variant shrink-0">
                <div class="min-w-0">
                    <h3 class="font-headline-md text-headline-md text-on-surface truncate">Certificate preview</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ $template->name }}</p>
                </div>
                <div class="flex items-center gap-sm shrink-0">
                    <x-loading-messages
                        x-show="previewModalLoading"
                        :messages="['Rendering preview…']"
                    />
                    <button type="button" class="btn-secondary h-10 px-md" @click="closePreviewModal()">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                        Close
                    </button>
                </div>
            </div>
            <div class="flex-1 overflow-auto p-md md:p-lg flex items-center justify-center" @click.self="closePreviewModal()">
                <div class="bg-white rounded-xl border border-outline-variant shadow-2xl p-sm md:p-md w-full max-w-[1100px]">
                    <img
                        x-show="previewMode === 'canvas'"
                        x-ref="modalPreviewImage"
                        alt="Certificate preview"
                        class="w-full h-auto max-h-[min(78vh,900px)] object-contain mx-auto"
                    >
                    <iframe
                        x-show="previewMode !== 'canvas'"
                        x-ref="modalPreviewFrame"
                        title="Certificate preview"
                        class="w-full min-h-[min(70vh,720px)] border-0 rounded-lg bg-white"
                    ></iframe>
                    <p class="font-body-sm text-body-sm text-on-surface-variant text-center mt-sm">
                        Sample preview — QR is a placeholder until you issue. Signature and logos use your profile.
                    </p>
                </div>
            </div>
        </div>

        {{-- Looks-good checklist before submit --}}
        <div
            x-show="checklistOpen"
            x-cloak
            class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-lg"
            @keydown.escape.window="checklistOpen = false"
        >
            <div class="bg-white rounded-xl border border-outline-variant shadow-xl max-w-md w-full p-lg space-y-md" @click.outside="checklistOpen = false">
                <div class="flex items-center justify-between">
                    <h3 class="font-headline-md text-headline-md text-on-surface">Looks good?</h3>
                    <button type="button" class="w-9 h-9 rounded-lg hover:bg-surface-variant flex items-center justify-center" @click="checklistOpen = false">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                <ul class="space-y-sm font-body-sm text-body-sm text-on-surface">
                    <li class="flex gap-sm items-center">
                        <span class="material-symbols-outlined text-[18px]" :class="checklist.title ? 'text-primary' : 'text-amber-600'" x-text="checklist.title ? 'check_circle' : 'error'"></span>
                        Certificate title filled
                    </li>
                    <li class="flex gap-sm items-center">
                        <span class="material-symbols-outlined text-[18px]" :class="checklist.recipient ? 'text-primary' : 'text-amber-600'" x-text="checklist.recipient ? 'check_circle' : 'error'"></span>
                        Recipient name filled
                    </li>
                    <li class="flex gap-sm items-center">
                        <span class="material-symbols-outlined text-[18px]" :class="checklist.date ? 'text-primary' : 'text-amber-600'" x-text="checklist.date ? 'check_circle' : 'error'"></span>
                        Issue date set
                    </li>
                    <li class="flex gap-sm items-center" x-show="checklist.checkSignature">
                        <span class="material-symbols-outlined text-[18px]" :class="checklist.signature ? 'text-primary' : 'text-amber-600'" x-text="checklist.signature ? 'check_circle' : 'error'"></span>
                        Signature on profile
                        <template x-if="!checklist.signature">
                            <a href="{{ route('profile.edit') }}" class="text-primary underline ml-auto">Fix</a>
                        </template>
                    </li>
                    <li class="flex gap-sm items-center" x-show="checklist.checkLogo">
                        <span class="material-symbols-outlined text-[18px]" :class="checklist.logo ? 'text-primary' : 'text-amber-600'" x-text="checklist.logo ? 'check_circle' : 'error'"></span>
                        Organization logo on profile
                        <template x-if="!checklist.logo">
                            <a href="{{ route('profile.edit') }}" class="text-primary underline ml-auto">Fix</a>
                        </template>
                    </li>
                </ul>
                <div class="flex gap-sm pt-sm">
                    <button type="button" class="btn-secondary flex-1" @click="checklistOpen = false">Keep editing</button>
                    <button type="button" class="btn-primary flex-1" @click="confirmIssue()" :disabled="!checklist.canSubmit || submitting">
                        Confirm &amp; Issue
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.contextual-shell>

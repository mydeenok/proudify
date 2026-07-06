<x-layouts.contextual-shell
    title="Create New Certificate"
    subtitle="Single Issuance"
    :back-url="route('templates.index')"
>
    <x-slot:actions>
        <a href="{{ route('certificates.create.canvas', ['template' => $template->id]) }}" class="btn-secondary h-10 px-md py-xs text-sm">
            <span class="material-symbols-outlined text-[18px]">draw</span>
            Use Visual Canvas
        </a>
        <button type="button" id="preview-certificate-btn" class="btn-secondary h-10 px-md py-xs text-sm">
            <span class="material-symbols-outlined text-[18px]">visibility</span>
            Preview Certificate
        </button>
        <button type="button" class="btn-secondary h-10 px-md py-xs text-sm" disabled title="Coming soon">
            <span class="material-symbols-outlined text-[18px]">save</span>
            Save Draft
        </button>
    </x-slot:actions>

    <form id="preview-launch-form" action="{{ route('certificates.preview') }}" method="POST" target="_blank" class="hidden">
        @csrf
        <input type="hidden" name="template_id" value="{{ $template->id }}">
        <input type="hidden" name="title" id="preview_launch_title">
        <input type="hidden" name="recipient_name" id="preview_launch_recipient_name">
        <input type="hidden" name="description" id="preview_launch_description">
        <input type="hidden" name="date_of_issue" id="preview_launch_date_of_issue">
        <input type="hidden" name="date_of_expiry" id="preview_launch_date_of_expiry">
    </form>

    <div class="max-w-[1440px] mx-auto w-full p-margin flex flex-col lg:flex-row gap-margin">
        <div class="w-full lg:w-5/12 flex flex-col gap-xl">
            <form
                method="POST"
                action="{{ route('certificates.store') }}"
                enctype="multipart/form-data"
                class="card-surface p-lg bento-shadow-sm space-y-md"
                x-data="{ submitting: false }"
                x-on:submit="submitting = true"
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
                        <x-text-input id="recipient_name" name="recipient_name" type="text" required placeholder="e.g. Jane Doe" :value="old('recipient_name')" />
                        <x-input-error :messages="$errors->get('recipient_name')" />
                    </div>
                    <div>
                        <x-input-label for="recipient_email" value="Recipient Email" />
                        <x-text-input id="recipient_email" name="recipient_email" type="email" required placeholder="jane@example.com" :value="old('recipient_email')" />
                        <x-input-error :messages="$errors->get('recipient_email')" />
                    </div>
                </div>

                <div>
                    <x-input-label for="title" value="Certificate Title" />
                    <x-text-input id="title" name="title" type="text" required placeholder="e.g. Certificate of Completion" :value="old('title')" />
                    <x-input-error :messages="$errors->get('title')" />
                </div>

                <div>
                    <x-input-label for="description" value="Description / Subtitle" />
                    <textarea id="description" name="description" rows="3" maxlength="500" class="form-input h-auto py-sm min-h-[88px]" placeholder="Enter custom text for the certificate body…">{{ old('description') }}</textarea>
                    <x-input-error :messages="$errors->get('description')" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                    <div>
                        <x-input-label for="date_of_issue" value="Issue Date" />
                        <x-text-input id="date_of_issue" name="date_of_issue" type="date" required :value="old('date_of_issue', now()->toDateString())" />
                        <x-input-error :messages="$errors->get('date_of_issue')" />
                    </div>
                    <div>
                        <x-input-label for="date_of_expiry" value="Expiry Date (optional)" />
                        <x-text-input id="date_of_expiry" name="date_of_expiry" type="date" :value="old('date_of_expiry')" />
                        <x-input-error :messages="$errors->get('date_of_expiry')" />
                    </div>
                </div>

                @if (count($customFields))
                    <div class="pt-md border-t border-outline-variant space-y-md">
                        <h2 class="font-headline-md text-headline-md text-on-surface">Template Fields</h2>

                        @foreach ($customFields as $field)
                            @php
                                $customFieldOldValue = old('custom_fields.'.$field['key']);
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
                                        {{ $field['required'] ? 'required' : '' }}
                                        class="form-input py-xs"
                                    />
                                @else
                                    <x-text-input
                                        id="custom-field-{{ $field['key'] }}"
                                        type="text"
                                        name="custom_fields[{{ $field['key'] }}]"
                                        data-custom-text-field="{{ $field['key'] }}"
                                        :value="$customFieldOldValue"
                                        :required="$field['required']"
                                    />
                                @endif
                                <x-input-error :messages="$customFieldErrors" />
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="pt-md border-t border-outline-variant flex gap-sm">
                    <a href="{{ route('templates.index') }}" class="btn-secondary flex-1">Cancel</a>
                    <x-primary-button class="btn-primary flex-1" x-bind:disabled="submitting">
                        <span x-show="!submitting">Issue Certificate</span>
                        <span x-show="!submitting" class="material-symbols-outlined text-[18px]">send</span>
                        <span x-show="submitting" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                        <span x-show="submitting">Issuing certificate…</span>
                    </x-primary-button>
                </div>
            </form>
        </div>

        <div class="w-full lg:w-7/12 flex flex-col" id="certificate-live-preview">
            <div class="glass-panel rounded-xl flex-1 p-md flex flex-col relative min-h-[500px]">
                <div class="flex justify-between items-center mb-md px-xs">
                    <h3 class="font-label-md text-label-md text-on-surface flex items-center gap-xs">
                        <span class="material-symbols-outlined text-primary text-[18px]">visibility</span>
                        Live Preview
                    </h3>
                    <div class="flex items-center gap-md">
                        <x-loading-messages
                            id="inline-preview-loading"
                            :messages="['Loading fonts…', 'Rendering your design…']"
                        />
                        <div class="flex gap-xs">
                            <button type="button" id="preview-zoom-in" class="w-8 h-8 rounded bg-surface border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[18px]">zoom_in</span>
                            </button>
                            <button type="button" id="preview-zoom-out" class="w-8 h-8 rounded bg-surface border border-outline-variant flex items-center justify-center text-on-surface-variant hover:text-primary transition-colors">
                                <span class="material-symbols-outlined text-[18px]">zoom_out</span>
                            </button>
                            <span id="preview-zoom-level" class="w-10 h-8 flex items-center justify-center font-label-sm text-label-sm text-on-surface-variant">100%</span>
                        </div>
                    </div>
                </div>

                <div class="flex-1 bg-surface-container-low rounded-lg border border-outline-variant flex items-center justify-center p-md overflow-hidden relative shadow-inner">
                    <div data-preview="canvas" class="bg-surface shadow-[0_8px_32px_rgba(0,0,0,0.1)] aspect-[1.414/1] w-full max-w-[640px] relative overflow-hidden border border-[#eaeaea] transition-transform origin-center">
                        <iframe id="certificate-preview-frame" title="Certificate preview" class="w-full h-full border-0" srcdoc="{{ $initialPreviewHtml }}"></iframe>
                    </div>
                </div>

                <p class="font-body-sm text-body-sm text-on-surface-variant mt-md text-center">Preview updates as you type · Template: <strong>{{ $template->name }}</strong></p>
            </div>
        </div>
    </div>
</x-layouts.contextual-shell>

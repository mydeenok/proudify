<x-layouts.contextual-shell
    title="Create New Certificate"
    subtitle="Visual Canvas — {{ $template->name }}"
    :back-url="route('certificates.create', ['template' => $template->id])"
>
    <x-slot:actions>
        <a href="{{ route('certificates.create', ['template' => $template->id]) }}" class="btn-secondary h-10 px-md py-xs text-sm">
            Use Form Instead
        </a>
    </x-slot:actions>

    @php
        $editableSystemBindings = ['title', 'recipient_name', 'description', 'date_of_issue', 'date_of_expiry'];
        $placeholderLabels = [
            'title' => 'Certificate Title',
            'recipient_name' => 'Recipient Name',
            'description' => 'Description',
        ];
    @endphp

    <form
        method="POST"
        action="{{ route('certificates.store') }}"
        enctype="multipart/form-data"
        class="max-w-[900px] mx-auto w-full p-margin space-y-md"
        x-data="{ submitting: false }"
        x-on:submit="submitting = true"
        data-no-loading-state
    >
        @csrf
        <input type="hidden" name="template_id" value="{{ $template->id }}">

        <div class="card-surface p-md bento-shadow-sm flex flex-col md:flex-row items-start md:items-center gap-md justify-between">
            <div class="w-full md:w-auto md:flex-1 max-w-sm">
                <x-input-label for="canvas_recipient_email" value="Recipient Email" />
                <x-text-input id="canvas_recipient_email" name="recipient_email" type="email" required placeholder="jane@example.com" :value="old('recipient_email')" />
                <x-input-error :messages="$errors->get('recipient_email')" />
            </div>
            <x-primary-button class="btn-primary" x-bind:disabled="submitting">
                <span x-show="!submitting">Issue Certificate</span>
                <span x-show="!submitting" class="material-symbols-outlined text-[18px]">send</span>
                <span x-show="submitting" class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>
                <span x-show="submitting">Issuing certificate…</span>
            </x-primary-button>
        </div>

        <p class="font-body-sm text-body-sm text-on-surface-variant text-center">
            Click directly on the design below to fill in each field — the layout itself can't be changed here, only the content.
        </p>

        <div class="flex justify-center overflow-auto p-md">
            <div
                id="canvas-issue-wrapper"
                class="bg-white shadow-xl border border-outline-variant relative shrink-0"
                style="width: {{ $canvasWidth }}px; height: {{ $canvasHeight }}px;"
            >
                <iframe
                    title="Certificate design"
                    class="absolute inset-0 w-full h-full border-0"
                    style="pointer-events: none;"
                    srcdoc="{{ $initialPreviewHtml }}"
                ></iframe>

                @foreach ($overlayElements as $element)
                    @php
                        $binding = $element['binding'];
                        $isDate = in_array($binding, ['date_of_issue', 'date_of_expiry'], true);
                        $isSystemField = in_array($binding, $editableSystemBindings, true);
                        $customField = $isSystemField ? null : collect($customFields)->firstWhere('key', $binding);
                        $isImage = $customField && $customField['type'] === 'image';
                        $inputName = $isSystemField ? $binding : ($isImage ? "custom_image_fields[{$binding}]" : "custom_fields[{$binding}]");
                        $oldKey = $isSystemField ? $binding : (($isImage ? 'custom_image_fields.' : 'custom_fields.').$binding);
                        $label = $isSystemField ? ($placeholderLabels[$binding] ?? $binding) : ($customField['label'] ?? $binding);
                        $required = $isSystemField ? in_array($binding, ['title', 'recipient_name'], true) : (bool) ($customField['required'] ?? false);

                        $style = sprintf(
                            'position:absolute;left:%s%%;top:%s%%;width:%s%%;height:%s%%;',
                            $element['xPercent'] ?? 0,
                            $element['yPercent'] ?? 0,
                            $element['widthPercent'] ?? 10,
                            $element['heightPercent'] ?? 10,
                        );

                        $textStyle = $element['style'] ?? [];
                        $fontStyle = sprintf(
                            'font-family:%s;font-size:%spx;font-weight:%s;color:%s;text-align:%s;',
                            $textStyle['fontFamily'] ?? 'Inter, sans-serif',
                            $textStyle['fontSize'] ?? 16,
                            $textStyle['fontWeight'] ?? '400',
                            $textStyle['color'] ?? '#151c27',
                            $textStyle['textAlign'] ?? 'left',
                        );
                    @endphp

                    @if ($isImage)
                        <label
                            style="{{ $style }}"
                            class="flex items-center justify-center text-center border-2 border-dashed border-primary/50 bg-primary/5 hover:bg-primary/10 cursor-pointer transition-colors overflow-hidden"
                            data-custom-image-overlay
                        >
                            <input type="file" name="{{ $inputName }}" accept="image/*" class="sr-only" {{ $required ? 'required' : '' }} data-custom-image-input>
                            <img data-custom-image-preview class="hidden absolute inset-0 w-full h-full object-contain bg-white" alt="{{ $label }}">
                            <span data-custom-image-hint class="font-body-sm text-body-sm text-primary px-xs">{{ $label }}</span>
                        </label>
                    @elseif ($isDate)
                        <input
                            type="date"
                            name="{{ $binding }}"
                            style="{{ $style }}"
                            value="{{ old($oldKey, $binding === 'date_of_issue' ? now()->toDateString() : '') }}"
                            {{ $required ? 'required' : '' }}
                            class="border border-outline-variant bg-white/90 rounded px-xs"
                        >
                    @else
                        <input
                            type="text"
                            name="{{ $inputName }}"
                            style="{{ $style }}{{ $fontStyle }}"
                            placeholder="{{ $label }}"
                            value="{{ old($oldKey) }}"
                            {{ $required ? 'required' : '' }}
                            class="bg-transparent border border-transparent hover:border-outline-variant focus:border-primary focus:bg-white/70 rounded px-xs outline-none"
                        >
                    @endif
                @endforeach
            </div>
        </div>
    </form>

    <script>
        document.querySelectorAll('[data-custom-image-input]').forEach((input) => {
            input.addEventListener('change', () => {
                const file = input.files?.[0];
                const wrapper = input.closest('[data-custom-image-overlay]');
                const preview = wrapper?.querySelector('[data-custom-image-preview]');
                const hint = wrapper?.querySelector('[data-custom-image-hint]');
                if (!file || !preview) return;

                const reader = new FileReader();
                reader.onload = () => {
                    preview.src = reader.result;
                    preview.classList.remove('hidden');
                    if (hint) hint.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            });
        });
    </script>
</x-layouts.contextual-shell>

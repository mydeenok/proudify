<x-layouts.contextual-shell
    title="Preview Certificate"
    subtitle="{{ $template->name }}"
>
    <div class="max-w-[1440px] mx-auto w-full p-margin flex flex-col lg:flex-row gap-margin h-full">
        <div class="w-full lg:w-5/12 flex flex-col gap-md">
            <form id="preview-form" class="card-surface p-lg bento-shadow-sm space-y-md">
                <input type="hidden" name="template_id" value="{{ $template->id }}">

                <h2 class="font-headline-md text-headline-md text-on-surface mb-lg">Edit &amp; preview</h2>

                <div>
                    <x-input-label for="preview_title" value="Certificate Title" />
                    <x-text-input id="preview_title" name="title" type="text" value="{{ $formData['title'] ?? '' }}" />
                </div>

                <div>
                    <x-input-label for="preview_recipient_name" value="Recipient Name" />
                    <x-text-input id="preview_recipient_name" name="recipient_name" type="text" value="{{ $formData['recipient_name'] ?? '' }}" />
                </div>

                <div>
                    <x-input-label for="preview_description" value="Description / Subtitle" />
                    <textarea id="preview_description" name="description" rows="3" class="form-input h-auto py-sm min-h-[88px]">{{ $formData['description'] ?? '' }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
                    <div>
                        <x-input-label for="preview_date_of_issue" value="Issue Date" />
                        <x-text-input id="preview_date_of_issue" name="date_of_issue" type="date" value="{{ $formData['date_of_issue'] ?? now()->toDateString() }}" />
                    </div>
                    <div>
                        <x-input-label for="preview_date_of_expiry" value="Expiry Date (optional)" />
                        <x-text-input id="preview_date_of_expiry" name="date_of_expiry" type="date" value="{{ $formData['date_of_expiry'] ?? '' }}" />
                    </div>
                </div>

                <p class="font-body-sm text-body-sm text-on-surface-variant/70">
                    This is a sample preview — the QR code links to a placeholder, not a real verification page. Your uploaded signature and organization logo (if any) render for real.
                </p>
            </form>
        </div>

        <div class="w-full lg:w-7/12 flex flex-col gap-sm">
            <div class="flex items-center justify-between px-xs">
                <h3 class="font-label-md text-label-md text-on-surface">Live Preview</h3>
                <x-loading-messages
                    id="preview-loading"
                    style="display: none;"
                    :messages="['Loading fonts…', 'Preparing your design…', 'Almost ready…']"
                />
            </div>
            <div class="flex-1 bg-surface-container-low rounded-lg border border-outline-variant overflow-hidden shadow-inner min-h-[500px]">
                <iframe id="preview-frame" title="Certificate preview" class="w-full h-full border-0" srcdoc="{{ $initialHtml }}"></iframe>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('preview-form');
            const frame = document.getElementById('preview-frame');
            const loading = document.getElementById('preview-loading');
            let debounceTimer = null;

            async function rerender() {
                loading.style.display = 'flex';

                try {
                    const response = await fetch('{{ route('certificates.preview.render') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            Accept: 'text/html',
                        },
                        body: new FormData(form),
                    });

                    if (response.ok) {
                        frame.srcdoc = await response.text();
                    }
                } finally {
                    loading.style.display = 'none';
                }
            }

            form.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(rerender, 400);
            });
        })();
    </script>
</x-layouts.contextual-shell>

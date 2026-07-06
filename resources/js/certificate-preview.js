/**
 * create_certificate_proudify_user — live preview panel.
 *
 * Renders the actual template (same CertificateRenderService::renderPreviewHtml
 * the standalone Preview Certificate page uses) inside an iframe, debounced
 * on input — replaces an earlier generic mockup-card preview that didn't
 * reflect the real design at all.
 */
export function initCertificateLivePreview() {
    const root = document.getElementById('certificate-live-preview');
    const frame = document.getElementById('certificate-preview-frame');
    if (!root || !frame) return;

    const templateId = document.getElementById('certificate_template_id')?.value;

    const fields = {
        title: document.getElementById('title'),
        recipient_name: document.getElementById('recipient_name'),
        description: document.getElementById('description'),
        date_of_issue: document.getElementById('date_of_issue'),
        date_of_expiry: document.getElementById('date_of_expiry'),
    };

    // Admin-defined custom text fields (see Template::custom_field_schema) —
    // collected by attribute rather than a fixed id list, since the set of
    // fields varies per template. Image-type custom fields have nothing to
    // preview live before upload, so they're intentionally left out here.
    const customTextFields = Array.from(document.querySelectorAll('[data-custom-text-field]'));

    const canvas = root.querySelector('[data-preview="canvas"]');
    const zoomIn = document.getElementById('preview-zoom-in');
    const zoomOut = document.getElementById('preview-zoom-out');
    const zoomLabel = document.getElementById('preview-zoom-level');
    const loading = document.getElementById('inline-preview-loading');

    let zoom = 1;
    let debounceTimer = null;

    async function rerender() {
        if (loading) loading.style.display = 'flex';

        try {
            const body = new URLSearchParams({
                template_id: templateId ?? '',
                title: fields.title?.value ?? '',
                recipient_name: fields.recipient_name?.value ?? '',
                description: fields.description?.value ?? '',
                date_of_issue: fields.date_of_issue?.value ?? '',
                date_of_expiry: fields.date_of_expiry?.value ?? '',
            });

            customTextFields.forEach((el) => {
                body.append(`custom_fields[${el.dataset.customTextField}]`, el.value ?? '');
            });

            const response = await fetch('/certificates/preview/render', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'text/html',
                },
                body,
            });

            if (response.ok) {
                frame.srcdoc = await response.text();
            }
        } finally {
            if (loading) loading.style.display = 'none';
        }
    }

    const applyZoom = () => {
        if (canvas) {
            canvas.style.transform = `scale(${zoom})`;
        }
        if (zoomLabel) {
            zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
        }
    };

    [...Object.values(fields), ...customTextFields].forEach((el) => el?.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(rerender, 400);
    }));

    zoomIn?.addEventListener('click', () => {
        zoom = Math.min(1.4, zoom + 0.1);
        applyZoom();
    });
    zoomOut?.addEventListener('click', () => {
        zoom = Math.max(0.6, zoom - 0.1);
        applyZoom();
    });

    applyZoom();

    // The iframe already has real markup via the server-rendered
    // initialPreviewHtml (no fetch needed for the first paint) - the
    // loading indicator must start hidden, since rerender() is the only
    // thing that ever toggles it and it doesn't run until the user
    // actually edits a field.
    if (loading) loading.style.display = 'none';

    initPreviewLaunch(fields, customTextFields);
}

/**
 * "Preview Certificate" button — mirrors whatever's currently typed into
 * the create-certificate form into a hidden target="_blank" form so the
 * standalone preview page opens in a new tab with the same values already
 * filled in. Custom text fields are appended as hidden inputs built fresh
 * each click (rather than fixed markup), since the set varies per template.
 */
function initPreviewLaunch(fields, customTextFields) {
    const button = document.getElementById('preview-certificate-btn');
    const launchForm = document.getElementById('preview-launch-form');
    if (!button || !launchForm) return;

    button.addEventListener('click', () => {
        document.getElementById('preview_launch_title').value = fields.title?.value ?? '';
        document.getElementById('preview_launch_recipient_name').value = fields.recipient_name?.value ?? '';
        document.getElementById('preview_launch_description').value = fields.description?.value ?? '';
        document.getElementById('preview_launch_date_of_issue').value = fields.date_of_issue?.value ?? '';
        document.getElementById('preview_launch_date_of_expiry').value = fields.date_of_expiry?.value ?? '';

        launchForm.querySelectorAll('[data-custom-field-hidden]').forEach((el) => el.remove());
        customTextFields.forEach((el) => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `custom_fields[${el.dataset.customTextField}]`;
            hidden.value = el.value ?? '';
            hidden.dataset.customFieldHidden = 'true';
            launchForm.appendChild(hidden);
        });

        launchForm.submit();
    });
}

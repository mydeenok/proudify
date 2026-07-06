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
            const response = await fetch('/certificates/preview/render', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    Accept: 'text/html',
                },
                body: new URLSearchParams({
                    template_id: templateId ?? '',
                    title: fields.title?.value ?? '',
                    recipient_name: fields.recipient_name?.value ?? '',
                    description: fields.description?.value ?? '',
                    date_of_issue: fields.date_of_issue?.value ?? '',
                    date_of_expiry: fields.date_of_expiry?.value ?? '',
                }),
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

    Object.values(fields).forEach((el) => el?.addEventListener('input', () => {
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

    initPreviewLaunch(fields);
}

/**
 * "Preview Certificate" button — mirrors whatever's currently typed into
 * the create-certificate form into a hidden target="_blank" form so the
 * standalone preview page opens in a new tab with the same values already
 * filled in.
 */
function initPreviewLaunch(fields) {
    const button = document.getElementById('preview-certificate-btn');
    const launchForm = document.getElementById('preview-launch-form');
    if (!button || !launchForm) return;

    button.addEventListener('click', () => {
        document.getElementById('preview_launch_title').value = fields.title?.value ?? '';
        document.getElementById('preview_launch_recipient_name').value = fields.recipient_name?.value ?? '';
        document.getElementById('preview_launch_description').value = fields.description?.value ?? '';
        document.getElementById('preview_launch_date_of_issue').value = fields.date_of_issue?.value ?? '';
        document.getElementById('preview_launch_date_of_expiry').value = fields.date_of_expiry?.value ?? '';
        launchForm.submit();
    });
}

/**
 * create_certificate_proudify_user — live preview panel.
 *
 * Renders the actual template (same CertificateRenderService::renderPreviewHtml
 * the standalone Preview Certificate page uses) inside an iframe, debounced
 * on input — replaces an earlier generic mockup-card preview that didn't
 * reflect the real design at all. Registered as Alpine.data() components
 * (see resources/views/certificates/create.blade.php for the x-data wiring)
 * rather than an imperative DOMContentLoaded script, so the component's
 * lifecycle and DOM refs are owned by Alpine instead of manual querySelector.
 */
export function registerCertificatePreview(Alpine) {
    Alpine.data('certificatePreview', (templateId) => ({
        submitting: false,
        loading: true,
        zoom: 1,
        baseScale: 1,
        debounceTimer: null,

        init() {
            // $refs for this component's own children (viewport/canvas/frame)
            // aren't populated yet at the moment a component's own init()
            // runs - Alpine resolves x-data on an element and calls init()
            // before it's walked that element's children to register their
            // x-ref bindings. $nextTick defers until after that walk
            // completes. Without this, referencing an unpopulated $refs.frame
            // throws synchronously, which - since this fires from inside
            // Alpine's initial Array.prototype.forEach over every x-data root
            // on the page - aborts that loop and silently leaves every other
            // component on the page (including unrelated ones later in the
            // DOM) never initialized.
            this.$nextTick(() => {
                this.baseScale = this.computeBaseScale();
                this.applyZoom();

                window.addEventListener('resize', () => {
                    this.baseScale = this.computeBaseScale();
                    this.applyZoom();
                });

                // The initial iframe content comes from server-rendered
                // initialPreviewHtml (no fetch needed for the first paint),
                // but the template's own fonts (Google Fonts etc loaded via
                // its html_content) still need to finish loading before the
                // design is actually done rendering - hiding the indicator
                // earlier than that would flash fallback-font text as if it
                // were the real design.
                waitForFrameFontsReady(this.$refs.frame).then(() => {
                    this.loading = false;
                });
            });
        },

        // The canvas renders at the certificate's real native pixel size
        // (see create.blade.php) so a template's own fixed-px decorations
        // stay proportional; baseScale is what shrinks that native-size
        // render down to fit the responsive viewport box, recomputed on
        // resize. The 60-140% zoom control multiplies on top of it.
        computeBaseScale() {
            const nativeWidth = parseFloat(this.$refs.canvas?.style.width) || 1000;
            return this.$refs.viewport && nativeWidth ? this.$refs.viewport.clientWidth / nativeWidth : 1;
        },

        applyZoom() {
            if (this.$refs.canvas) {
                this.$refs.canvas.style.transform = `scale(${this.baseScale * this.zoom})`;
            }
        },

        zoomIn() {
            this.zoom = Math.min(1.4, this.zoom + 0.1);
            this.applyZoom();
        },

        zoomOut() {
            this.zoom = Math.max(0.6, this.zoom - 0.1);
            this.applyZoom();
        },

        scheduleRerender() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.rerender(), 400);
        },

        async rerender() {
            this.loading = true;

            try {
                const body = new URLSearchParams({
                    template_id: templateId ?? '',
                    title: this.$refs.title?.value ?? '',
                    recipient_name: this.$refs.recipient_name?.value ?? '',
                    description: this.$refs.description?.value ?? '',
                    date_of_issue: this.$refs.date_of_issue?.value ?? '',
                    date_of_expiry: this.$refs.date_of_expiry?.value ?? '',
                });

                // Admin-defined custom text fields (see
                // Template::custom_field_schema) — collected by attribute
                // rather than a fixed id list, since the set of fields
                // varies per template. Image-type custom fields have
                // nothing to preview live before upload, so they're
                // intentionally left out here.
                this.$el.querySelectorAll('[data-custom-text-field]').forEach((el) => {
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
                    this.$refs.frame.srcdoc = await response.text();
                    await waitForFrameFontsReady(this.$refs.frame);
                }
            } finally {
                this.loading = false;
            }
        },
    }));

    // "Preview Certificate" button — mirrors whatever's currently typed
    // into the create-certificate form into a hidden target="_blank" form
    // so the standalone preview page opens in a new tab with the same
    // values already filled in. Lives in the page-header actions slot, a
    // separate DOM subtree from the form/preview panel above, so it reads
    // current field values by id rather than sharing that component's
    // scope. Custom text fields are appended as hidden inputs built fresh
    // each click (rather than fixed markup), since the set varies per
    // template.
    Alpine.data('previewLaunch', () => ({
        launch() {
            document.getElementById('preview_launch_title').value = document.getElementById('title')?.value ?? '';
            document.getElementById('preview_launch_recipient_name').value = document.getElementById('recipient_name')?.value ?? '';
            document.getElementById('preview_launch_description').value = document.getElementById('description')?.value ?? '';
            document.getElementById('preview_launch_date_of_issue').value = document.getElementById('date_of_issue')?.value ?? '';
            document.getElementById('preview_launch_date_of_expiry').value = document.getElementById('date_of_expiry')?.value ?? '';

            const launchForm = document.getElementById('preview-launch-form');
            launchForm.querySelectorAll('[data-custom-field-hidden]').forEach((el) => el.remove());
            document.querySelectorAll('[data-custom-text-field]').forEach((el) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = `custom_fields[${el.dataset.customTextField}]`;
                hidden.value = el.value ?? '';
                hidden.dataset.customFieldHidden = 'true';
                launchForm.appendChild(hidden);
            });

            launchForm.submit();
        },
    }));
}

/**
 * Resolves once the iframe has finished loading AND its document's fonts
 * are ready — not just once the HTML has parsed. A `srcdoc` iframe already
 * present in the initial page markup may have finished loading by the time
 * this runs (no `load` event left to catch), so `readyState` is checked
 * first rather than unconditionally waiting for the event.
 */
function waitForFrameFontsReady(frame) {
    const settle = (doc) => (doc?.fonts?.ready ? doc.fonts.ready.catch(() => {}) : Promise.resolve());

    if (frame.contentDocument?.readyState === 'complete') {
        return settle(frame.contentDocument);
    }

    return new Promise((resolve) => {
        frame.addEventListener('load', () => resolve(settle(frame.contentDocument)), { once: true });
    });
}

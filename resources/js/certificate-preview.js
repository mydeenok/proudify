/**
 * create_certificate_proudify_user — live preview panel.
 *
 * Canvas templates paint via the same Node/Skia engine as the issued PDF
 * (image/png returned from /certificates/preview/render). Legacy HTML
 * templates keep the iframe/srcdoc path. Registered as Alpine.data()
 * components (see resources/views/certificates/create.blade.php).
 */
export function registerCertificatePreview(Alpine) {
    Alpine.data('certificatePreview', (templateId, previewMode = 'html', options = {}) => ({
        submitting: false,
        loading: true,
        zoom: 1,
        baseScale: 1,
        debounceTimer: null,
        draftTimer: null,
        previewMode,
        draftSaveUrl: options.draftSaveUrl ?? '/certificates/drafts',
        draftDeleteUrl: options.draftDeleteUrl ?? '/certificates/drafts',
        fieldHighlights: options.fieldHighlights ?? [],
        stepDraft: !!options.hasDraft,
        stepPreview: false,
        stepIssue: false,
        draftStatus: options.hasDraft ? 'Draft restored' : '',
        activeHighlight: null,
        checklistOpen: false,
        previewModalOpen: false,
        previewModalLoading: false,
        checklist: {
            title: false,
            recipient: false,
            date: false,
            signature: !!options.profileHasSignature,
            logo: !!options.profileHasLogo,
            checkLogo: !!options.templateNeedsLogo,
            checkSignature: !!options.templateNeedsSignature,
            canSubmit: false,
        },
        profileHasSignature: !!options.profileHasSignature,
        profileHasLogo: !!options.profileHasLogo,
        templateNeedsLogo: !!options.templateNeedsLogo,
        templateNeedsSignature: !!options.templateNeedsSignature,

        get highlightStyle() {
            const rect = this.activeHighlight;
            if (!rect) return '';

            return [
                `left:${rect.xPercent}%`,
                `top:${rect.yPercent}%`,
                `width:${rect.widthPercent}%`,
                `height:${rect.heightPercent}%`,
            ].join(';');
        },

        init() {
            // $refs for this component's own children (viewport/canvas/frame)
            // aren't populated yet at the moment a component's own init()
            // runs - Alpine resolves x-data on an element and calls init()
            // before it's walked that element's children to register their
            // x-ref bindings. $nextTick defers until after that walk
            // completes.
            this.$nextTick(() => {
                this.baseScale = this.computeBaseScale();
                this.applyZoom();
                this.refreshIssueStep();

                window.addEventListener('resize', () => {
                    this.baseScale = this.computeBaseScale();
                    this.applyZoom();
                });

                this.settleInitialPaint().then(() => {
                    this.loading = false;
                    this.stepPreview = true;
                });
            });
        },

        settleInitialPaint() {
            if (this.previewMode === 'canvas') {
                return waitForImageReady(this.$refs.previewImage);
            }

            return waitForFrameFontsReady(this.$refs.frame);
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

        onFieldInput() {
            this.refreshIssueStep();
            this.scheduleRerender();
            this.scheduleDraftSave();
        },

        markPreviewStale() {
            this.refreshIssueStep();
            this.scheduleDraftSave();
        },

        scheduleRerender() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.rerender(), 400);
        },

        scheduleDraftSave() {
            clearTimeout(this.draftTimer);
            this.draftStatus = 'Saving draft…';
            this.draftTimer = setTimeout(() => this.saveDraft(), 2000);
        },

        async saveDraftNow() {
            clearTimeout(this.draftTimer);
            await this.saveDraft();
        },

        async saveDraft() {
            try {
                const body = this.buildFormBody();
                const response = await fetch(this.draftSaveUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        Accept: 'application/json',
                    },
                    body,
                });

                if (!response.ok) {
                    this.draftStatus = 'Draft save failed';
                    return;
                }

                this.stepDraft = true;
                this.draftStatus = 'Draft saved';
            } catch (error) {
                this.draftStatus = 'Draft save failed';
            }
        },

        buildFormBody() {
            const body = new URLSearchParams({
                template_id: templateId ?? '',
                title: this.$refs.title?.value ?? document.getElementById('title')?.value ?? '',
                recipient_name: this.$refs.recipient_name?.value ?? document.getElementById('recipient_name')?.value ?? '',
                recipient_email: this.$refs.recipient_email?.value ?? document.getElementById('recipient_email')?.value ?? '',
                description: this.$refs.description?.value ?? document.getElementById('description')?.value ?? '',
                date_of_issue: this.$refs.date_of_issue?.value ?? document.getElementById('date_of_issue')?.value ?? '',
                date_of_expiry: this.$refs.date_of_expiry?.value ?? document.getElementById('date_of_expiry')?.value ?? '',
            });

            this.$el.querySelectorAll('[data-custom-text-field]').forEach((el) => {
                body.append(`custom_fields[${el.dataset.customTextField}]`, el.value ?? '');
            });

            return body;
        },

        setHighlight(binding) {
            if (this.previewMode !== 'canvas') {
                this.activeHighlight = null;
                return;
            }

            this.activeHighlight = this.fieldHighlights.find((item) => item.binding === binding) ?? null;
        },

        clearHighlight() {
            this.activeHighlight = null;
        },

        refreshIssueStep() {
            const title = (this.$refs.title?.value ?? document.getElementById('title')?.value ?? '').trim();
            const recipient = (this.$refs.recipient_name?.value ?? document.getElementById('recipient_name')?.value ?? '').trim();
            const email = (this.$refs.recipient_email?.value ?? document.getElementById('recipient_email')?.value ?? '').trim();
            const date = (this.$refs.date_of_issue?.value ?? document.getElementById('date_of_issue')?.value ?? '').trim();
            this.stepIssue = !!(title && recipient && email && date);
        },

        openChecklist() {
            const title = (document.getElementById('title')?.value ?? '').trim();
            const recipient = (document.getElementById('recipient_name')?.value ?? '').trim();
            const date = (document.getElementById('date_of_issue')?.value ?? '').trim();

            this.checklist = {
                title: !!title,
                recipient: !!recipient,
                date: !!date,
                signature: this.profileHasSignature,
                logo: this.profileHasLogo,
                checkLogo: this.templateNeedsLogo,
                checkSignature: this.templateNeedsSignature,
                canSubmit: !!(
                    title
                    && recipient
                    && date
                    && (!this.templateNeedsSignature || this.profileHasSignature)
                    && (!this.templateNeedsLogo || this.profileHasLogo)
                ),
            };
            this.checklistOpen = true;
        },

        confirmIssue() {
            if (!this.checklist.canSubmit || this.submitting) return;
            this.submitting = true;
            this.checklistOpen = false;
            document.getElementById('certificate-create-form')?.submit();
        },

        async openPreviewModal() {
            this.previewModalOpen = true;
            this.previewModalLoading = true;
            document.body.classList.add('overflow-hidden');

            try {
                await this.rerender();
                this.syncModalPreview();
                this.stepPreview = true;
            } finally {
                this.previewModalLoading = false;
            }
        },

        closePreviewModal() {
            this.previewModalOpen = false;
            this.previewModalLoading = false;
            document.body.classList.remove('overflow-hidden');
        },

        syncModalPreview() {
            if (this.previewMode === 'canvas') {
                const src = this.$refs.previewImage?.src;
                if (this.$refs.modalPreviewImage && src) {
                    this.$refs.modalPreviewImage.src = src;
                }
                return;
            }

            if (this.$refs.modalPreviewFrame && this.$refs.frame) {
                this.$refs.modalPreviewFrame.srcdoc = this.$refs.frame.srcdoc || '';
            }
        },

        async rerender() {
            this.loading = true;

            try {
                const body = this.buildFormBody();
                const response = await fetch('/certificates/preview/render', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        Accept: 'image/png, text/html',
                    },
                    body,
                });

                if (!response.ok) {
                    return;
                }

                const mode = response.headers.get('X-Preview-Mode') || this.previewMode;

                if (mode === 'canvas') {
                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    const previous = this.$refs.previewImage?.src;
                    if (this.$refs.previewImage) {
                        this.$refs.previewImage.src = url;
                        await waitForImageReady(this.$refs.previewImage);
                    }
                    if (previous && previous.startsWith('blob:')) {
                        URL.revokeObjectURL(previous);
                    }
                    this.previewMode = 'canvas';
                } else if (this.$refs.frame) {
                    this.$refs.frame.srcdoc = await response.text();
                    await waitForFrameFontsReady(this.$refs.frame);
                    this.previewMode = 'html';
                }

                this.stepPreview = true;
                if (this.previewModalOpen) {
                    this.syncModalPreview();
                }
            } finally {
                this.loading = false;
            }
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
    if (!frame) {
        return Promise.resolve();
    }

    const settle = (doc) => (doc?.fonts?.ready ? doc.fonts.ready.catch(() => {}) : Promise.resolve());

    if (frame.contentDocument?.readyState === 'complete') {
        return settle(frame.contentDocument);
    }

    return new Promise((resolve) => {
        frame.addEventListener('load', () => resolve(settle(frame.contentDocument)), { once: true });
    });
}

function waitForImageReady(image) {
    if (!image) {
        return Promise.resolve();
    }

    if (image.complete && image.naturalWidth > 0) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        image.addEventListener('load', () => resolve(), { once: true });
        image.addEventListener('error', () => resolve(), { once: true });
    });
}

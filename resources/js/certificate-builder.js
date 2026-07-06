import * as fabric from 'fabric';
import Alpine from 'alpinejs';

// This page is its own standalone Vite entry (not app.js), so Alpine
// needs starting here too — the background-import loading indicator
// (x-loading-messages) depends on it.
window.Alpine = Alpine;
Alpine.start();

/**
 * Visual certificate builder. Deliberately does NOT use Fabric's native
 * toJSON()/fromJSON() as the storage format — canvas_json is our own
 * {id, type, binding, xPercent, yPercent, widthPercent, heightPercent,
 * rotation, z, content, style} schema (see LayoutToHtmlRenderer.php),
 * stored as percentages so the editing canvas and Browsershot's render
 * viewport never need to agree on an exact pixel size. Fabric objects are
 * rebuilt fresh from that schema on load, not deserialized natively.
 */
const root = document.getElementById('certificate-builder');
if (root) {
    const config = JSON.parse(root.dataset.config);
    const canvasSize = config.orientation === 'portrait' ? { width: 707, height: 1000 } : { width: 1000, height: 707 };

    const canvas = new fabric.Canvas('builder-canvas', {
        width: canvasSize.width,
        height: canvasSize.height,
        // Transparent when a background iframe is present, so its design
        // shows through wherever no Fabric object has been drawn yet.
        backgroundColor: config.backgroundHtml ? 'transparent' : '#ffffff',
    });

    let nextId = 1;
    const generateId = () => `el_${Date.now()}_${nextId++}`;

    const BOUND_FIELD_LABELS = {
        recipient_name: 'Recipient Name',
        title: 'Certificate Title',
        description: 'Description',
        date_of_issue: 'Issue Date',
        date_of_expiry: 'Expiry Date',
        organization_name: 'Organization Name',
        verification_code: 'Verification Code',
    };

    const PLACEHOLDER_LABELS = {
        qrcode: 'QR CODE',
        signature: 'SIGNATURE',
        company_logo: 'LOGO',
    };

    function addText({ binding = null, content = 'Double-click to edit' } = {}) {
        const text = binding ? `{${BOUND_FIELD_LABELS[binding] ?? binding}}` : content;

        const obj = new fabric.Textbox(text, {
            left: canvasSize.width / 2 - 150,
            top: canvasSize.height / 2 - 20,
            width: 300,
            fontSize: 24,
            fontFamily: 'Inter',
            fontWeight: '400',
            fill: '#151c27',
            textAlign: 'center',
            originX: 'left',
            originY: 'top',
        });
        obj.data = { id: generateId(), type: 'text', binding };

        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
    }

    function addPlaceholder(type, label) {
        const width = 150;
        const height = 150;

        const rect = new fabric.Rect({
            width,
            height,
            fill: 'rgba(180, 0, 18, 0.06)',
            stroke: '#b40012',
            strokeDashArray: [6, 4],
            rx: 8,
            ry: 8,
        });
        const text = new fabric.FabricText(label, {
            fontSize: 14,
            fontFamily: 'Inter',
            fill: '#b40012',
            originX: 'center',
            originY: 'center',
            left: width / 2,
            top: height / 2,
        });

        const group = new fabric.Group([rect, text], {
            left: canvasSize.width / 2 - width / 2,
            top: canvasSize.height / 2 - height / 2,
            originX: 'left',
            originY: 'top',
        });
        group.data = { id: generateId(), type, binding: type };

        canvas.add(group);
        canvas.setActiveObject(group);
        canvas.requestRenderAll();
    }

    function deleteSelected() {
        const active = canvas.getActiveObject();
        if (active) {
            canvas.remove(active);
            canvas.requestRenderAll();
        }
    }

    // --- Serialization: our schema, not Fabric's native format ---

    function serialize() {
        const elements = canvas.getObjects().map((obj, index) => {
            const width = obj.getScaledWidth();
            const height = obj.getScaledHeight();

            const base = {
                id: obj.data?.id ?? generateId(),
                type: obj.data?.type ?? 'text',
                binding: obj.data?.binding ?? null,
                xPercent: (obj.left / canvasSize.width) * 100,
                yPercent: (obj.top / canvasSize.height) * 100,
                widthPercent: (width / canvasSize.width) * 100,
                heightPercent: (height / canvasSize.height) * 100,
                rotation: obj.angle ?? 0,
                z: index,
            };

            if (base.type === 'text') {
                base.content = base.binding ? null : obj.text;
                base.style = {
                    fontFamily: obj.fontFamily,
                    fontSize: Math.round(obj.fontSize * (obj.scaleY ?? 1)),
                    fontWeight: String(obj.fontWeight),
                    color: obj.fill,
                    textAlign: obj.textAlign,
                };
            }

            return base;
        });

        return { elements };
    }

    function deserialize(canvasJson) {
        const elements = canvasJson?.elements ?? [];

        elements
            .slice()
            .sort((a, b) => a.z - b.z)
            .forEach((el) => {
                const left = (el.xPercent / 100) * canvasSize.width;
                const top = (el.yPercent / 100) * canvasSize.height;
                const width = (el.widthPercent / 100) * canvasSize.width;

                if (el.type === 'qrcode' || el.type === 'signature' || el.type === 'company_logo') {
                    addPlaceholder(el.type, PLACEHOLDER_LABELS[el.type] ?? el.type.toUpperCase());
                    const obj = canvas.getActiveObject();
                    obj.set({ left, top, angle: el.rotation ?? 0 });
                    obj.scaleToWidth(width);
                    obj.data.id = el.id;
                } else {
                    addText({ binding: el.binding, content: el.content ?? '' });
                    const obj = canvas.getActiveObject();
                    obj.set({
                        left,
                        top,
                        width,
                        angle: el.rotation ?? 0,
                        fontFamily: el.style?.fontFamily ?? 'Inter',
                        fontSize: el.style?.fontSize ?? 24,
                        fontWeight: el.style?.fontWeight ?? '400',
                        fill: el.style?.color ?? '#151c27',
                        textAlign: el.style?.textAlign ?? 'left',
                    });
                    obj.data.id = el.id;
                }
            });

        canvas.discardActiveObject();
        canvas.requestRenderAll();
    }

    // --- Persistence ---

    async function persist(url) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': config.csrfToken,
                Accept: 'application/json',
            },
            body: JSON.stringify({ canvas_json: serialize() }),
        });

        return response.json();
    }

    // --- Wire up toolbar ---

    const activeToolClasses = ['text-primary', 'bg-primary-fixed'];
    const inactiveToolClasses = ['text-on-surface-variant'];

    function setActiveTool(name) {
        document.querySelectorAll('[data-builder-tool]').forEach((btn) => {
            const isActive = btn.dataset.builderTool === name;
            btn.classList.toggle('text-primary', isActive);
            btn.classList.toggle('bg-primary-fixed', isActive);
            btn.classList.toggle('text-on-surface-variant', !isActive);
            const icon = btn.querySelector('.material-symbols-outlined');
            if (icon) {
                icon.style.fontVariationSettings = isActive ? "'FILL' 1" : "'FILL' 0";
            }
        });
    }

    document.getElementById('tool-add-text')?.addEventListener('click', () => {
        addText();
        setActiveTool('text');
    });

    document.getElementById('bound-field-select')?.addEventListener('change', (event) => {
        const binding = event.target.value;
        if (binding) {
            addText({ binding });
            setActiveTool('text');
            event.target.value = '';
        }
    });

    document.getElementById('tool-add-qr')?.addEventListener('click', () => {
        addPlaceholder('qrcode', 'QR CODE');
        setActiveTool('qr');
    });
    document.getElementById('tool-add-signature')?.addEventListener('click', () => {
        addPlaceholder('signature', 'SIGNATURE');
        setActiveTool('signature');
    });
    document.getElementById('tool-delete')?.addEventListener('click', deleteSelected);

    document.addEventListener('keydown', (event) => {
        if ((event.key === 'Delete' || event.key === 'Backspace') && !canvas.getActiveObject()?.isEditing) {
            const active = document.activeElement;
            const isTypingInPage = active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA');
            if (!isTypingInPage) deleteSelected();
        }
    });

    // --- Property panel ---

    const propPanel = document.getElementById('property-panel');
    const propFontSize = document.getElementById('prop-font-size');
    const propColor = document.getElementById('prop-color');
    const propAlign = document.getElementById('prop-align');

    function refreshPropertyPanel() {
        const active = canvas.getActiveObject();
        const isText = active?.data?.type === 'text';

        propPanel.classList.toggle('hidden', !isText);
        if (isText) {
            propFontSize.value = Math.round(active.fontSize);
            propColor.value = active.fill;
            propAlign.value = active.textAlign;
        }
    }

    canvas.on('selection:created', refreshPropertyPanel);
    canvas.on('selection:updated', refreshPropertyPanel);
    canvas.on('selection:cleared', refreshPropertyPanel);

    propFontSize?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active) {
            active.set('fontSize', Number(event.target.value));
            canvas.requestRenderAll();
        }
    });

    propColor?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active) {
            active.set('fill', event.target.value);
            canvas.requestRenderAll();
        }
    });

    propAlign?.addEventListener('change', (event) => {
        const active = canvas.getActiveObject();
        if (active) {
            active.set('textAlign', event.target.value);
            canvas.requestRenderAll();
        }
    });

    // --- Save / Publish ---

    const statusLabel = document.getElementById('builder-status');

    document.getElementById('save-draft-btn')?.addEventListener('click', async () => {
        statusLabel.textContent = 'Saving…';
        await persist(config.saveUrl);
        statusLabel.textContent = 'Draft saved';
        setTimeout(() => (statusLabel.textContent = ''), 2000);
    });

    document.getElementById('publish-btn')?.addEventListener('click', async () => {
        if (canvas.getObjects().length === 0) {
            alert('Add at least one element before publishing.');
            return;
        }
        statusLabel.textContent = 'Publishing…';
        const result = await persist(config.publishUrl);
        if (result.redirect) window.location.href = result.redirect;
    });

    // --- Zoom ---

    let zoom = 1;
    const zoomLabel = document.getElementById('zoom-level');
    const canvasWrapper = document.getElementById('canvas-wrapper');

    function applyZoom() {
        canvasWrapper.style.transform = `scale(${zoom})`;
        zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
    }

    document.getElementById('zoom-in')?.addEventListener('click', () => {
        zoom = Math.min(2, zoom + 0.1);
        applyZoom();
    });
    document.getElementById('zoom-out')?.addEventListener('click', () => {
        zoom = Math.max(0.4, zoom - 0.1);
        applyZoom();
    });

    applyZoom();

    // --- Background import & auto-detect ---
    //
    // Every template's html_content is hand-written at creation time —
    // canvas_json only exists after the first builder save. A background
    // iframe shows that raw HTML so the admin isn't editing on a blank
    // canvas; when it hasn't been imported yet (config.needsAutoDetect),
    // the server marks each known placeholder with a data-bind attribute
    // (see TemplateBackgroundImportService) and this walks them once the
    // iframe paints, promoting each one to a real, draggable/deletable
    // canvas object at roughly its on-page position.

    const backgroundFrame = document.getElementById('builder-background-frame');
    const backgroundLoading = document.getElementById('builder-background-loading');

    function autoDetectFromBackground() {
        const doc = backgroundFrame.contentDocument;
        if (!doc) return;

        const frameWidth = backgroundFrame.clientWidth || canvasSize.width;
        const frameHeight = backgroundFrame.clientHeight || canvasSize.height;

        doc.querySelectorAll('[data-bind]').forEach((node) => {
            const binding = node.getAttribute('data-bind');
            const rect = node.getBoundingClientRect();

            const left = (rect.left / frameWidth) * canvasSize.width;
            const top = (rect.top / frameHeight) * canvasSize.height;
            const width = (rect.width / frameWidth) * canvasSize.width;

            if (binding === 'qrcode' || binding === 'signature' || binding === 'company_logo') {
                addPlaceholder(binding, PLACEHOLDER_LABELS[binding] ?? binding.toUpperCase());
                const obj = canvas.getActiveObject();
                obj.set({ left, top });
                obj.scaleToWidth(Math.max(width, 20));
            } else {
                addText({ binding, content: '' });
                const obj = canvas.getActiveObject();
                obj.set({ left, top, width: Math.max(width, 40) });
            }
        });

        canvas.discardActiveObject();
        // Synchronous, not requestRenderAll() — this runs inside an async
        // iframe-load/font-ready handler, and relying on the next animation
        // frame to paint proved unreliable there (e.g. a backgrounded tab).
        canvas.renderAll();
    }

    async function onBackgroundReady() {
        // Wait for web fonts too, not just the load event — Google Fonts
        // swap in asynchronously, and measuring positions before that swap
        // would place detected fields using fallback-font metrics instead
        // of the template's real layout.
        const doc = backgroundFrame.contentDocument;
        if (doc?.fonts?.ready) {
            await doc.fonts.ready.catch(() => {});
        }

        if (config.needsAutoDetect) {
            autoDetectFromBackground();
        }
        if (backgroundLoading) backgroundLoading.style.display = 'none';
    }

    if (backgroundFrame) {
        // `srcdoc` starts parsing as soon as the browser sees the attribute
        // in markup — by the time this module executes, the iframe may
        // have already finished loading, so a `load` listener attached now
        // would never fire. Check readyState first and only fall back to
        // the event for the (also real) case where it's still in flight.
        if (backgroundFrame.contentDocument?.readyState === 'complete') {
            onBackgroundReady();
        } else {
            backgroundFrame.addEventListener('load', onBackgroundReady, { once: true });
        }
    }

    // --- Initial load ---

    setActiveTool('text');

    if (config.canvasJson) {
        deserialize(config.canvasJson);
    }
}

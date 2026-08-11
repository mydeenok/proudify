import * as fabric from 'fabric';
import Alpine from 'alpinejs';

/**
 * Loaded here (not app.css) so only the builder page pays for these — and so
 * the live canvas preview uses the exact same font files the Node renderer
 * registers server-side for the final PDF (see fontManifest() in
 * CertificateCanvasRenderService.php). Keep these two lists in sync.
 */
import '@fontsource/noto-sans/400.css';
import '@fontsource/noto-sans/700.css';
import '@fontsource/noto-sans-tamil/400.css';
import '@fontsource/noto-sans-tamil/700.css';
import '@fontsource/playfair-display/400.css';
import '@fontsource/playfair-display/700.css';
import '@fontsource/carlito/400.css';
import '@fontsource/carlito/700.css';
import '@fontsource/cinzel/400.css';
import '@fontsource/cinzel/700.css';
import '@fontsource/cormorant-garamond/400.css';
import '@fontsource/cormorant-garamond/700.css';
import '@fontsource/dancing-script/400.css';
import '@fontsource/great-vibes/400.css';
import '@fontsource/pacifico/400.css';
import '@fontsource/montserrat/400.css';
import '@fontsource/montserrat/700.css';
import '@fontsource/lora/400.css';
import '@fontsource/lora/700.css';

window.Alpine = Alpine;
Alpine.start();

/**
 * Visual certificate builder. Deliberately does NOT use Fabric's native
 * toJSON()/fromJSON() as the storage format — canvas_json is our own
 * percent-based schema (see LayoutToHtmlRenderer.php / CertificateCanvas-
 * RenderService.php). Fabric objects are rebuilt from that schema on load.
 */
const root = document.getElementById('certificate-builder');
if (root) {
    const config = JSON.parse(root.dataset.config);
    const canvasSize = config.orientation === 'portrait' ? { width: 707, height: 1000 } : { width: 1000, height: 707 };
    const SNAP_TOLERANCE = 5;

    const canvas = new fabric.Canvas('builder-canvas', {
        width: canvasSize.width,
        height: canvasSize.height,
        backgroundColor: config.backgroundHtml ? 'transparent' : '#ffffff',
    });

    // Re-paint once the @fontsource faces finish parsing — objects created
    // before then would otherwise keep the browser's fallback glyph metrics.
    document.fonts?.ready?.then(() => canvas.requestRenderAll()).catch(() => {});

    let nextId = 1;
    const generateId = () => `el_${Date.now()}_${nextId++}`;

    // --- Undo / Redo (memento: full canvas_json snapshots) ---
    const undoStack = [];
    const redoStack = [];
    let historySuspended = false;
    let pageBackground = config.pageBackground ?? { type: 'color', value: '#ffffff' };

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

    function slugify(label) {
        return label
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') || `field_${generateId()}`;
    }

    let toastTimer = null;
    function showBuilderToast(message, tone = 'info') {
        const el = document.getElementById('builder-toast');
        if (!el) return;
        el.textContent = message;
        el.classList.remove('hidden', 'bg-white', 'border-outline-variant', 'text-on-surface', 'bg-red-50', 'border-red-200', 'text-red-700', 'bg-amber-50', 'border-amber-200', 'text-amber-900');
        if (tone === 'error') {
            el.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
        } else if (tone === 'warn') {
            el.classList.add('bg-amber-50', 'border-amber-200', 'text-amber-900');
        } else {
            el.classList.add('bg-white', 'border-outline-variant', 'text-on-surface');
        }
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => el.classList.add('hidden'), 3200);
    }

    // Shows a spinner on an upload trigger button while an async upload is
    // in flight, restoring its original content afterwards either way.
    async function withButtonSpinner(buttonId, task) {
        const btn = document.getElementById(buttonId);
        if (!btn) return task();

        const originalHtml = btn.innerHTML;
        const originalDisabled = btn.disabled;
        const spinnerHtml = '<span class="material-symbols-outlined text-[20px] animate-spin">progress_activity</span>';
        // Swap only the icon glyph if one is present, otherwise the whole label.
        btn.innerHTML = btn.querySelector('.material-symbols-outlined')
            ? btn.innerHTML.replace(/<span class="material-symbols-outlined[^>]*>[^<]*<\/span>/, spinnerHtml)
            : spinnerHtml;
        btn.disabled = true;

        try {
            return await task();
        } finally {
            btn.innerHTML = originalHtml;
            btn.disabled = originalDisabled;
        }
    }

    function isOverlayUiOpen() {
        const presetOpen = !document.getElementById('preset-confirm-modal')?.classList.contains('hidden');
        const previewOpen = !document.getElementById('sample-preview-drawer')?.classList.contains('hidden');
        return !!(presetOpen || previewOpen);
    }

    function pushHistory() {
        if (historySuspended) return;
        undoStack.push(JSON.stringify(serialize()));
        if (undoStack.length > 50) undoStack.shift();
        redoStack.length = 0;
        refreshHistoryButtons();
        refreshEmptyHint();
        if (typeof markDirty === 'function') {
            markDirty();
        }
    }

    function refreshHistoryButtons() {
        const undoBtn = document.getElementById('undo-btn');
        const redoBtn = document.getElementById('redo-btn');
        if (undoBtn) undoBtn.disabled = undoStack.length === 0;
        if (redoBtn) redoBtn.disabled = redoStack.length === 0;
    }

    function undo() {
        if (!undoStack.length) return;
        redoStack.push(JSON.stringify(serialize()));
        const snapshot = JSON.parse(undoStack.pop());
        historySuspended = true;
        applySnapshot(snapshot);
        historySuspended = false;
        refreshHistoryButtons();
    }

    function redo() {
        if (!redoStack.length) return;
        undoStack.push(JSON.stringify(serialize()));
        const snapshot = JSON.parse(redoStack.pop());
        historySuspended = true;
        applySnapshot(snapshot);
        historySuspended = false;
        refreshHistoryButtons();
    }

    function applySnapshot(snapshot) {
        canvas.clear();
        pageBackground = snapshot.background ?? { type: 'color', value: '#ffffff' };
        applyPageBackground().then(() => {
            deserialize(snapshot);
            refreshLayersPanel();
            canvas.requestRenderAll();
        });
    }

    // --- Elements ---

    /**
     * Fabric represents a gradient fill as a Gradient *instance* assigned to
     * `.fill` (not a plain color string), so it can't round-trip through
     * obj.fill the way a solid color does. obj.data.gradient is the real
     * source of truth for the panel + serialize(); this just (re)builds the
     * live Fabric object whenever the box is created/resized or the two
     * stop colors change.
     */
    function buildTextGradient({ from, to }) {
        return new fabric.Gradient({
            type: 'linear',
            // Percentage units so the gradient stays correct if the text
            // box is later resized, instead of freezing to the pixel width
            // it happened to have when the gradient was turned on.
            gradientUnits: 'percentage',
            coords: { x1: 0, y1: 0, x2: 1, y2: 0 },
            colorStops: [
                { offset: 0, color: from },
                { offset: 1, color: to },
            ],
        });
    }

    function addText({ binding = null, content = 'Double-click to edit', label = null } = {}) {
        const text = binding ? `{${label ?? BOUND_FIELD_LABELS[binding] ?? binding}}` : content;

        const obj = new fabric.Textbox(text, {
            left: canvasSize.width / 2 - 150,
            top: canvasSize.height / 2 - 20,
            width: 300,
            fontSize: 24,
            fontFamily: 'Inter',
            fontWeight: '400',
            fill: '#151c27',
            textAlign: 'center',
            lineHeight: 1.2,
            charSpacing: 0,
            paintFirst: 'stroke',
            originX: 'left',
            originY: 'top',
        });
        obj.data = {
            id: generateId(),
            type: 'text',
            binding,
            label,
            // Kept independently of `fill` (a Fabric.Gradient instance once
            // gradient fill is on) so the plain color picker + serialized
            // fallback color always have a real hex value to read.
            textColor: '#151c27',
            gradient: null,
            autoFit: false,
        };

        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        pushHistory();
        refreshLayersPanel();
    }

    function addPlaceholder(type, label, binding = type, extraData = {}) {
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
        group.data = { id: generateId(), type, binding, ...extraData };

        canvas.add(group);
        canvas.setActiveObject(group);
        canvas.requestRenderAll();
        pushHistory();
        refreshLayersPanel();
    }

    // Polygon-based shape kinds share one vertex generator so the Fabric
    // preview and the Node/@napi-rs/canvas PDF renderer (see shapeVertices()
    // in node/certificate-canvas-render.mjs) always draw the same geometry.
    const POLYGON_SHAPE_KINDS = ['triangle', 'diamond', 'pentagon', 'hexagon', 'star', 'seal', 'arrow', 'banner'];

    function regularPolygonPoints(sides, w, h) {
        const cx = w / 2;
        const cy = h / 2;
        const rx = w / 2;
        const ry = h / 2;
        const start = -Math.PI / 2;
        const points = [];

        for (let i = 0; i < sides; i++) {
            const angle = start + (i * 2 * Math.PI) / sides;
            points.push({ x: cx + rx * Math.cos(angle), y: cy + ry * Math.sin(angle) });
        }

        return points;
    }

    /**
     * Regular N-gons (odd side counts especially) don't naturally touch all
     * 4 edges of their own bounding box, so Fabric's auto-computed
     * width/height would be a little smaller than the box we intended.
     * Left uncorrected, re-deriving vertices from a previously-saved
     * (already slightly-short) width/height on every reload would shrink
     * the shape a bit more each time. Rescale raw vertices to exactly span
     * [0,w]x[0,h] so the fit is exact and stable across save/reload cycles.
     * Mirrors normalizeToBox() in node/certificate-canvas-render.mjs.
     */
    function normalizeToBox(points, w, h) {
        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;

        points.forEach(({ x, y }) => {
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;
        });

        const boundsWidth = maxX - minX || 1;
        const boundsHeight = maxY - minY || 1;

        return points.map(({ x, y }) => ({
            x: ((x - minX) / boundsWidth) * w,
            y: ((y - minY) / boundsHeight) * h,
        }));
    }

    function starPolygonPoints(spikes, innerRatio, w, h) {
        const cx = w / 2;
        const cy = h / 2;
        const outerRx = w / 2;
        const outerRy = h / 2;
        const innerRx = outerRx * innerRatio;
        const innerRy = outerRy * innerRatio;
        const start = -Math.PI / 2;
        const step = Math.PI / spikes;
        const points = [];

        for (let i = 0; i < spikes * 2; i++) {
            const angle = start + i * step;
            const rx = i % 2 === 0 ? outerRx : innerRx;
            const ry = i % 2 === 0 ? outerRy : innerRy;
            points.push({ x: cx + rx * Math.cos(angle), y: cy + ry * Math.sin(angle) });
        }

        return points;
    }

    function polygonPoints(kind, w, h) {
        switch (kind) {
            case 'triangle':
                return [{ x: w / 2, y: 0 }, { x: w, y: h }, { x: 0, y: h }];
            case 'diamond':
                return [{ x: w / 2, y: 0 }, { x: w, y: h / 2 }, { x: w / 2, y: h }, { x: 0, y: h / 2 }];
            case 'pentagon':
                return normalizeToBox(regularPolygonPoints(5, w, h), w, h);
            case 'hexagon':
                return normalizeToBox(regularPolygonPoints(6, w, h), w, h);
            case 'star':
                return normalizeToBox(starPolygonPoints(5, 0.5, w, h), w, h);
            case 'seal':
                return normalizeToBox(starPolygonPoints(24, 0.88, w, h), w, h);
            case 'arrow':
                return [
                    { x: 0, y: 0.3 * h }, { x: 0.6 * w, y: 0.3 * h }, { x: 0.6 * w, y: 0 },
                    { x: w, y: h / 2 }, { x: 0.6 * w, y: h }, { x: 0.6 * w, y: 0.7 * h }, { x: 0, y: 0.7 * h },
                ];
            case 'banner':
                return [
                    { x: 0.08 * w, y: 0 }, { x: 0.92 * w, y: 0 }, { x: w, y: h / 2 },
                    { x: 0.92 * w, y: h }, { x: 0.08 * w, y: h }, { x: 0, y: h / 2 },
                ];
            default:
                return null;
        }
    }

    // Control-point envelope of the curves below, spanning x:[-0.05,1.05] /
    // y:[0,0.85] in unit space (the two top bulges overshoot left/right).
    // Remapped onto exactly [0,w]x[0,h] so the heart is stable across
    // save/reload cycles — kept in sync with heartX()/heartY()/traceHeart()
    // in node/certificate-canvas-render.mjs.
    const HEART_BOUNDS = { minX: -0.05, maxX: 1.05, minY: 0, maxY: 0.85 };

    function heartPathData(w, h) {
        const X = (u) => ((u - HEART_BOUNDS.minX) / (HEART_BOUNDS.maxX - HEART_BOUNDS.minX)) * w;
        const Y = (u) => ((u - HEART_BOUNDS.minY) / (HEART_BOUNDS.maxY - HEART_BOUNDS.minY)) * h;

        return `M ${X(0.5)} ${Y(0.85)} `
            + `C ${X(0.15)} ${Y(0.6)} ${X(-0.05)} ${Y(0.35)} ${X(0.15)} ${Y(0.15)} `
            + `C ${X(0.3)} ${Y(0)} ${X(0.5)} ${Y(0.1)} ${X(0.5)} ${Y(0.3)} `
            + `C ${X(0.5)} ${Y(0.1)} ${X(0.7)} ${Y(0)} ${X(0.85)} ${Y(0.15)} `
            + `C ${X(1.05)} ${Y(0.35)} ${X(0.85)} ${Y(0.6)} ${X(0.5)} ${Y(0.85)} Z`;
    }

    function addShape(shapeKind) {
        let obj;
        const fill = 'rgba(180, 0, 18, 0.06)';
        const stroke = '#b40012';
        const strokeWidth = 3;

        if (shapeKind === 'circle') {
            obj = new fabric.Ellipse({
                left: canvasSize.width / 2 - 60,
                top: canvasSize.height / 2 - 60,
                rx: 60,
                ry: 60,
                fill, stroke, strokeWidth,
                originX: 'left',
                originY: 'top',
            });
        } else if (shapeKind === 'line') {
            obj = new fabric.Rect({
                left: canvasSize.width / 2 - 150,
                top: canvasSize.height / 2,
                width: 300,
                height: 4,
                fill: '#151c27',
                stroke: '#151c27',
                strokeWidth: 0,
                originX: 'left',
                originY: 'top',
            });
        } else if (shapeKind === 'heart') {
            const w = 160;
            const h = 150;
            obj = new fabric.Path(heartPathData(w, h), {
                left: canvasSize.width / 2 - w / 2,
                top: canvasSize.height / 2 - h / 2,
                fill, stroke, strokeWidth,
                originX: 'left',
                originY: 'top',
            });
        } else if (POLYGON_SHAPE_KINDS.includes(shapeKind)) {
            const w = 180;
            const h = 150;
            obj = new fabric.Polygon(polygonPoints(shapeKind, w, h), {
                left: canvasSize.width / 2 - w / 2,
                top: canvasSize.height / 2 - h / 2,
                fill, stroke, strokeWidth,
                originX: 'left',
                originY: 'top',
            });
        } else {
            obj = new fabric.Rect({
                left: canvasSize.width / 2 - 100,
                top: canvasSize.height / 2 - 60,
                width: 200,
                height: 120,
                fill, stroke, strokeWidth,
                rx: 0,
                ry: 0,
                originX: 'left',
                originY: 'top',
            });
        }

        obj.data = {
            id: generateId(),
            type: 'shape',
            shapeKind,
            binding: null,
            label: shapeKind.charAt(0).toUpperCase() + shapeKind.slice(1),
        };

        canvas.add(obj);
        canvas.setActiveObject(obj);
        canvas.requestRenderAll();
        pushHistory();
        refreshLayersPanel();
    }

    async function addDecorativeImageFromAsset(uploaded) {
        const img = await fabric.FabricImage.fromURL(uploaded.url);
        const maxW = 200;
        const scale = Math.min(1, maxW / (img.width || maxW));
        img.set({
            left: canvasSize.width / 2 - (img.width * scale) / 2,
            top: canvasSize.height / 2 - (img.height * scale) / 2,
            scaleX: scale,
            scaleY: scale,
            originX: 'left',
            originY: 'top',
        });
        img.data = {
            id: generateId(),
            type: 'image',
            binding: null,
            label: 'Decorative',
            src: uploaded.path,
            previewUrl: uploaded.url,
        };

        canvas.add(img);
        canvas.setActiveObject(img);
        canvas.requestRenderAll();
        pushHistory();
        refreshLayersPanel();
        refreshEmptyHint();
    }

    function deleteSelected() {
        const active = canvas.getActiveObject();
        if (active) {
            canvas.remove(active);
            canvas.discardActiveObject();
            canvas.requestRenderAll();
            pushHistory();
            refreshLayersPanel();
            refreshPropertyPanel();
        }
    }

    // --- Serialization ---

    function serialize() {
        const elements = canvas.getObjects()
            .filter((obj) => !obj.data?.isGuide && !obj.data?.isSafeArea)
            .map((obj, index) => {
                const width = obj.getScaledWidth();
                const height = obj.getScaledHeight();

                const base = {
                    id: obj.data?.id ?? generateId(),
                    type: obj.data?.type ?? 'text',
                    binding: obj.data?.binding ?? null,
                    label: obj.data?.label ?? null,
                    xPercent: (obj.left / canvasSize.width) * 100,
                    yPercent: (obj.top / canvasSize.height) * 100,
                    widthPercent: (width / canvasSize.width) * 100,
                    heightPercent: (height / canvasSize.height) * 100,
                    rotation: obj.angle ?? 0,
                    z: index,
                    locked: !!obj.data?.locked,
                };

                if (base.type === 'text') {
                    base.content = base.binding ? null : obj.text;
                    base.style = {
                        fontFamily: obj.fontFamily,
                        fontSize: Math.round(obj.fontSize * (obj.scaleY ?? 1)),
                        fontWeight: String(obj.fontWeight),
                        fontStyle: obj.fontStyle || 'normal',
                        underline: !!obj.underline,
                        linethrough: !!obj.linethrough,
                        // obj.fill is a Fabric.Gradient instance (not a hex
                        // string) whenever gradient fill is on — the last
                        // picked solid color always lives in obj.data.textColor.
                        color: obj.data?.textColor ?? '#151c27',
                        textAlign: obj.textAlign,
                        opacity: typeof obj.opacity === 'number' ? obj.opacity : 1,
                        lineHeight: obj.lineHeight ?? 1.2,
                        letterSpacing: obj.charSpacing ?? 0,
                        autoFit: !!obj.data?.autoFit,
                        shadow: obj.shadow ? {
                            color: obj.shadow.color,
                            blur: obj.shadow.blur,
                            offsetX: obj.shadow.offsetX,
                            offsetY: obj.shadow.offsetY,
                        } : null,
                        outline: (obj.stroke && (obj.strokeWidth ?? 0) > 0) ? {
                            color: obj.stroke,
                            width: obj.strokeWidth,
                        } : null,
                        highlight: (obj.textBackgroundColor && obj.textBackgroundColor !== 'transparent') ? {
                            color: obj.textBackgroundColor,
                        } : null,
                        gradient: obj.data?.gradient ? { ...obj.data.gradient } : null,
                    };
                }

                if (base.type === 'shape') {
                    base.shapeKind = obj.data?.shapeKind ?? 'rect';
                    base.style = {
                        fill: obj.fill === 'transparent' || obj.fill === '' ? 'transparent' : obj.fill,
                        stroke: obj.stroke || '#151c27',
                        strokeWidth: obj.strokeWidth ?? 2,
                        borderRadius: obj.rx ?? 0,
                        opacity: typeof obj.opacity === 'number' ? obj.opacity : 1,
                        flipX: !!obj.flipX,
                        flipY: !!obj.flipY,
                    };
                }

                if (base.type === 'image' && !base.binding && obj.data?.src) {
                    base.src = obj.data.src;
                }

                // Historical templates labelled a custom-image slot
                // "Company Logo" (slug → binding company_logo, type image).
                // Normalize on save so the canvas PDF path resolves issuer
                // org logos instead of looking in custom_image_fields.
                if (
                    base.type === 'image'
                    && (base.binding === 'company_logo' || /^company_logo_\d+$/.test(base.binding ?? ''))
                ) {
                    base.type = 'company_logo';
                }

                return base;
            });

        return { elements, background: pageBackground };
    }

    function deserialize(canvasJson) {
        const elements = canvasJson?.elements ?? [];

        elements
            .slice()
            .sort((a, b) => (a.z ?? 0) - (b.z ?? 0))
            .forEach((el) => {
                const left = (el.xPercent / 100) * canvasSize.width;
                const top = (el.yPercent / 100) * canvasSize.height;
                const width = (el.widthPercent / 100) * canvasSize.width;
                const height = (el.heightPercent / 100) * canvasSize.height;

                if (el.type === 'shape') {
                    restoreShape(el, left, top, width, height);
                } else if (el.type === 'image' && !el.binding && el.src) {
                    restoreDecorativeImage(el, left, top, width, height);
                } else if (el.type === 'qrcode' || el.type === 'signature' || el.type === 'company_logo') {
                    addPlaceholderSilent(el.type, PLACEHOLDER_LABELS[el.type] ?? el.type.toUpperCase(), el.type);
                    const obj = canvas.getActiveObject();
                    applyPlaceholderBox(obj, left, top, width, height, el.rotation ?? 0);
                    obj.data.id = el.id;
                } else if (el.type === 'image') {
                    const label = el.label ?? el.binding ?? 'IMAGE';
                    addPlaceholderSilent('image', label.toUpperCase(), el.binding, { label: el.label });
                    const obj = canvas.getActiveObject();
                    applyPlaceholderBox(obj, left, top, width, height, el.rotation ?? 0);
                    obj.data.id = el.id;
                } else {
                    addTextSilent({ binding: el.binding, content: el.content ?? '', label: el.label });
                    const obj = canvas.getActiveObject();
                    const style = el.style ?? {};
                    const textColor = style.color ?? '#151c27';

                    obj.set({
                        left,
                        top,
                        width,
                        angle: el.rotation ?? 0,
                        fontFamily: style.fontFamily ?? 'Inter',
                        fontSize: style.fontSize ?? 24,
                        fontWeight: style.fontWeight ?? '400',
                        fontStyle: style.fontStyle ?? 'normal',
                        underline: !!style.underline,
                        linethrough: !!style.linethrough,
                        fill: textColor,
                        textAlign: style.textAlign ?? 'left',
                        opacity: typeof style.opacity === 'number' ? style.opacity : 1,
                        lineHeight: style.lineHeight ?? 1.2,
                        charSpacing: style.letterSpacing ?? 0,
                        textBackgroundColor: style.highlight?.color ?? '',
                        stroke: style.outline?.color ?? '',
                        strokeWidth: style.outline ? (style.outline.width ?? 2) : 0,
                        paintFirst: 'stroke',
                        shadow: style.shadow ? new fabric.Shadow({
                            color: style.shadow.color ?? '#00000066',
                            blur: style.shadow.blur ?? 4,
                            offsetX: style.shadow.offsetX ?? 2,
                            offsetY: style.shadow.offsetY ?? 2,
                        }) : null,
                        scaleX: 1,
                        scaleY: 1,
                    });
                    obj.data.id = el.id;
                    obj.data.textColor = textColor;
                    obj.data.autoFit = !!style.autoFit;
                    obj.data.gradient = style.gradient ? { ...style.gradient } : null;

                    if (obj.data.gradient) {
                        obj.set('fill', buildTextGradient(obj.data.gradient));
                    }
                }

                // Shapes / decorative images may not be the active object after
                // restore — resolve by id so locked state survives reload.
                const restored = canvas.getObjects().find((o) => o.data?.id === el.id);
                if (restored && el.locked) {
                    applyLockState(restored, true);
                }
            });

        canvas.discardActiveObject();
        canvas.requestRenderAll();
        refreshLayersPanel();
    }

    // Silent variants skip pushHistory during bulk load/restore.
    function addTextSilent(opts) {
        const prev = historySuspended;
        historySuspended = true;
        addText(opts);
        historySuspended = prev;
    }

    function addPlaceholderSilent(type, label, binding = type, extraData = {}) {
        const prev = historySuspended;
        historySuspended = true;
        addPlaceholder(type, label, binding, extraData);
        historySuspended = prev;
    }

    /**
     * Placeholders are Fabric Groups authored at a fixed 150×150 base size.
     * scaleToWidth alone keeps them square, so a wide signature slot blows
     * past the page. Set independent scaleX/scaleY so widthPercent and
     * heightPercent are both honored (and label text stays centered).
     */
    function applyPlaceholderBox(obj, left, top, width, height, angle = 0) {
        if (!obj) return;
        const baseW = obj.width || 1;
        const baseH = obj.height || 1;
        obj.set({
            left,
            top,
            angle,
            scaleX: width / baseW,
            scaleY: height / baseH,
        });
        obj.setCoords();
    }

    function restoreShape(el, left, top, width, height) {
        const style = el.style ?? {};
        const common = {
            left, top,
            fill: style.fill ?? 'transparent',
            stroke: style.stroke ?? '#b40012',
            strokeWidth: style.strokeWidth ?? 3,
            angle: el.rotation ?? 0,
            opacity: typeof style.opacity === 'number' ? style.opacity : 1,
            flipX: !!style.flipX,
            flipY: !!style.flipY,
            originX: 'left',
            originY: 'top',
        };
        let obj;

        if (el.shapeKind === 'circle') {
            obj = new fabric.Ellipse({ ...common, rx: width / 2, ry: height / 2 });
        } else if (el.shapeKind === 'heart') {
            obj = new fabric.Path(heartPathData(width, height), common);
        } else if (POLYGON_SHAPE_KINDS.includes(el.shapeKind)) {
            obj = new fabric.Polygon(polygonPoints(el.shapeKind, width, height), common);
        } else {
            obj = new fabric.Rect({
                ...common,
                width, height,
                rx: style.borderRadius ?? 0,
                ry: style.borderRadius ?? 0,
            });
        }

        obj.data = {
            id: el.id,
            type: 'shape',
            shapeKind: el.shapeKind ?? 'rect',
            binding: null,
            label: el.label ?? el.shapeKind,
            locked: !!el.locked,
        };
        canvas.add(obj);
        if (el.locked) {
            applyLockState(obj, true);
        }
    }

    function restoreDecorativeImage(el, left, top, width, height) {
        // Preview URL is reconstructed from /storage/... path convention.
        const previewUrl = el.src.startsWith('http') ? el.src : `/storage/${el.src}`;

        loadDecorativeFabricImage(previewUrl).then((img) => {
            const scaleX = width / (img.width || width || 1);
            const scaleY = height / (img.height || height || 1);
            img.set({
                left, top,
                scaleX, scaleY,
                angle: el.rotation ?? 0,
                originX: 'left',
                originY: 'top',
            });
            img.data = {
                id: el.id,
                type: 'image',
                binding: null,
                label: el.label ?? 'Decorative',
                src: el.src,
                previewUrl,
            };
            canvas.add(img);
            canvas.requestRenderAll();
            refreshLayersPanel();
        }).catch(() => {
            addPlaceholderSilent('image', (el.label ?? 'IMAGE').toUpperCase(), null, { label: el.label, src: el.src });
            const obj = canvas.getActiveObject();
            applyPlaceholderBox(obj, left, top, width, height, el.rotation ?? 0);
            obj.data.id = el.id;
            obj.data.src = el.src;
        });
    }

    // --- Background ---

    async function applyPageBackground() {
        if (config.backgroundHtml) {
            return;
        }

        if (pageBackground?.type === 'image' && (pageBackground.previewUrl || config.pageBackgroundUrl)) {
            const url = pageBackground.previewUrl || config.pageBackgroundUrl;
            try {
                // Same-origin /storage URLs — do NOT set crossOrigin or
                // the browser may refuse to paint the image.
                const img = await fabric.FabricImage.fromURL(url);
                const scale = Math.max(canvasSize.width / img.width, canvasSize.height / img.height);
                img.set({
                    originX: 'left',
                    originY: 'top',
                    scaleX: scale,
                    scaleY: scale,
                    left: (canvasSize.width - img.width * scale) / 2,
                    top: (canvasSize.height - img.height * scale) / 2,
                });
                canvas.backgroundImage = img;
                canvas.backgroundColor = '#ffffff';
                setBgStatus('Background image applied');
            } catch (error) {
                console.error('Background image failed to load', url, error);
                canvas.backgroundImage = undefined;
                canvas.backgroundColor = '#ffffff';
                setBgStatus('Could not load background image');
            }
        } else {
            canvas.backgroundImage = undefined;
            canvas.backgroundColor = pageBackground?.value || '#ffffff';
            setBgStatus('');
        }

        canvas.requestRenderAll();
    }

    async function uploadAsset(file) {
        const body = new FormData();
        body.append('file', file);

        const response = await fetch(config.assetUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': config.csrfToken,
                Accept: 'application/json',
            },
            body,
        });

        if (!response.ok) {
            showBuilderToast('Upload failed. Please try a smaller image.', 'error');
            return null;
        }

        return response.json();
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

    // --- Layers panel ---

    function layerLabel(obj) {
        if (obj.data?.type === 'text') {
            return obj.data.binding
                ? (obj.data.label ?? BOUND_FIELD_LABELS[obj.data.binding] ?? obj.data.binding)
                : (obj.text || 'Text').slice(0, 24);
        }
        if (obj.data?.type === 'shape') return obj.data.label || obj.data.shapeKind || 'Shape';
        if (obj.data?.type === 'image') return obj.data.label || obj.data.binding || 'Image';
        return PLACEHOLDER_LABELS[obj.data?.type] ?? obj.data?.type ?? 'Layer';
    }

    function refreshLayersPanel() {
        const list = document.getElementById('layers-list');
        if (!list) return;

        const objects = canvas.getObjects().filter((o) => !o.data?.isGuide && !o.data?.isSafeArea);
        list.innerHTML = '';

        [...objects].reverse().forEach((obj, reverseIndex) => {
            const realIndex = objects.length - 1 - reverseIndex;
            const li = document.createElement('li');
            li.className = 'flex items-center gap-xs px-sm py-xs rounded-lg hover:bg-surface-variant cursor-pointer font-body-sm text-body-sm text-on-surface';
            li.draggable = !obj.data?.locked;
            li.dataset.index = String(realIndex);
            const lockIcon = obj.data?.locked ? 'lock' : 'lock_open';
            li.innerHTML = `<span class="material-symbols-outlined text-[16px] text-on-surface-variant">drag_indicator</span><span class="truncate flex-1">${layerLabel(obj)}</span><button type="button" class="layer-lock-btn w-7 h-7 flex items-center justify-center rounded hover:bg-white/80 text-on-surface-variant" data-id="${obj.data?.id ?? ''}" title="Toggle lock" data-tooltip="Toggle lock"><span class="material-symbols-outlined text-[16px]">${lockIcon}</span></button>`;

            li.addEventListener('click', (event) => {
                if (event.target.closest('.layer-lock-btn')) return;
                if (obj.data?.locked) return;
                canvas.setActiveObject(obj);
                canvas.requestRenderAll();
                refreshPropertyPanel();
            });

            li.querySelector('.layer-lock-btn')?.addEventListener('click', (event) => {
                event.stopPropagation();
                applyLockState(obj, !obj.data?.locked);
                canvas.requestRenderAll();
                pushHistory();
                refreshLayersPanel();
            });

            li.addEventListener('dragstart', (event) => {
                event.dataTransfer.setData('text/plain', String(realIndex));
            });

            li.addEventListener('dragover', (event) => event.preventDefault());

            li.addEventListener('drop', (event) => {
                event.preventDefault();
                const from = Number(event.dataTransfer.getData('text/plain'));
                const to = Number(li.dataset.index);
                if (Number.isNaN(from) || Number.isNaN(to) || from === to) return;

                const moving = objects[from];
                canvas.moveObjectTo(moving, to);
                canvas.requestRenderAll();
                pushHistory();
                refreshLayersPanel();
            });

            list.appendChild(li);
        });
    }

    // --- Snapping ---

    let guideLines = [];

    function clearGuides() {
        guideLines.forEach((g) => canvas.remove(g));
        guideLines = [];
    }

    function drawGuide(orientation, position) {
        const coords = orientation === 'V'
            ? [position, 0, position, canvasSize.height]
            : [0, position, canvasSize.width, position];

        const line = new fabric.Line(coords, {
            stroke: '#b40012',
            strokeWidth: 1,
            selectable: false,
            evented: false,
            opacity: 0.7,
        });
        line.data = { isGuide: true };
        canvas.add(line);
        guideLines.push(line);
    }

    function snapObject(moving) {
        clearGuides();

        const movingBound = {
            left: moving.left,
            top: moving.top,
            right: moving.left + moving.getScaledWidth(),
            bottom: moving.top + moving.getScaledHeight(),
            centerX: moving.left + moving.getScaledWidth() / 2,
            centerY: moving.top + moving.getScaledHeight() / 2,
        };

        const stops = {
            V: [0, canvasSize.width / 2, canvasSize.width],
            H: [0, canvasSize.height / 2, canvasSize.height],
        };

        canvas.getObjects().forEach((other) => {
            if (other === moving || other.data?.isGuide) return;
            stops.V.push(other.left, other.left + other.getScaledWidth() / 2, other.left + other.getScaledWidth());
            stops.H.push(other.top, other.top + other.getScaledHeight() / 2, other.top + other.getScaledHeight());
        });

        const movingEdges = {
            V: [
                { guide: movingBound.left, offset: 0 },
                { guide: movingBound.centerX, offset: -moving.getScaledWidth() / 2 },
                { guide: movingBound.right, offset: -moving.getScaledWidth() },
            ],
            H: [
                { guide: movingBound.top, offset: 0 },
                { guide: movingBound.centerY, offset: -moving.getScaledHeight() / 2 },
                { guide: movingBound.bottom, offset: -moving.getScaledHeight() },
            ],
        };

        let bestV = null;
        let bestH = null;

        stops.V.forEach((stop) => {
            movingEdges.V.forEach((edge) => {
                const diff = Math.abs(stop - edge.guide);
                if (diff <= SNAP_TOLERANCE && (!bestV || diff < bestV.diff)) {
                    bestV = { stop, offset: edge.offset, diff };
                }
            });
        });

        stops.H.forEach((stop) => {
            movingEdges.H.forEach((edge) => {
                const diff = Math.abs(stop - edge.guide);
                if (diff <= SNAP_TOLERANCE && (!bestH || diff < bestH.diff)) {
                    bestH = { stop, offset: edge.offset, diff };
                }
            });
        });

        if (bestV) {
            moving.set({ left: bestV.stop + bestV.offset });
            drawGuide('V', bestV.stop);
        }
        if (bestH) {
            moving.set({ top: bestH.stop + bestH.offset });
            drawGuide('H', bestH.stop);
        }
    }

    canvas.on('object:moving', (event) => {
        if (event.target && !event.target.data?.isGuide) {
            snapObject(event.target);
        }
    });

    canvas.on('object:modified', () => {
        clearGuides();
        pushHistory();
        refreshLayersPanel();
    });

    // --- Left rail + sliding tool panel ---

    let activePanel = null;
    const toolPanel = document.getElementById('tool-panel');
    if (toolPanel) toolPanel.style.width = '280px';

    function openPanel(name) {
        const collapsing = activePanel === name && toolPanel && toolPanel.style.width !== '0px';

        if (toolPanel) {
            toolPanel.style.width = collapsing ? '0px' : '280px';
            toolPanel.addEventListener('transitionend', () => fitToWorkspace(), { once: true });
        }

        activePanel = collapsing ? null : name;

        document.querySelectorAll('[data-panel-section]').forEach((section) => {
            section.classList.toggle('hidden', section.dataset.panelSection !== activePanel);
        });

        document.querySelectorAll('.rail-tab').forEach((btn) => {
            const isActive = btn.dataset.panel === activePanel;
            btn.classList.toggle('is-active', isActive);
            btn.classList.toggle('text-primary', isActive);
            btn.classList.toggle('text-on-surface-variant', !isActive);
            const icon = btn.querySelector('.material-symbols-outlined');
            if (icon) icon.style.fontVariationSettings = isActive ? "'FILL' 1" : "'FILL' 0";
        });
    }

    document.querySelectorAll('.rail-tab').forEach((btn) => {
        btn.addEventListener('click', () => openPanel(btn.dataset.panel));
    });

    // --- Text panel ---

    document.getElementById('add-heading-btn')?.addEventListener('click', () => {
        addText({ content: 'Add a heading' });
        const obj = canvas.getActiveObject();
        if (obj) {
            obj.set({ fontSize: 40, fontWeight: '700' });
            canvas.requestRenderAll();
        }
    });

    document.getElementById('add-body-btn')?.addEventListener('click', () => {
        addText({ content: 'Add a little body text' });
        const obj = canvas.getActiveObject();
        if (obj) {
            obj.set({ fontSize: 16, fontWeight: '400' });
            canvas.requestRenderAll();
        }
    });

    document.querySelectorAll('.field-chip').forEach((chip) => {
        chip.addEventListener('click', () => addText({ binding: chip.dataset.field }));
    });

    document.getElementById('add-custom-text-btn')?.addEventListener('click', () => {
        const label = prompt('Label for this custom field (e.g. "Course Name")');
        if (!label) return;
        addText({ binding: slugify(label), content: '', label });
    });

    // --- Fields panel ---

    document.getElementById('tool-add-image')?.addEventListener('click', () => {
        const label = prompt('Label for this custom image field (e.g. "Course Logo")');
        if (!label) return;
        const binding = slugify(label);
        addPlaceholder('image', label.toUpperCase(), binding, { label });
    });

    document.getElementById('tool-add-qr')?.addEventListener('click', () => addPlaceholder('qrcode', 'QR CODE'));
    document.getElementById('tool-add-signature')?.addEventListener('click', () => addPlaceholder('signature', 'SIGNATURE'));
    document.getElementById('tool-add-company-logo')?.addEventListener('click', () => addPlaceholder('company_logo', 'COMPANY LOGO'));

    // --- Elements panel (shapes + vectors) ---

    document.querySelectorAll('.shape-tool-btn').forEach((btn) => {
        btn.addEventListener('click', () => addShape(btn.dataset.shapeKind));
    });
    document.getElementById('tool-delete')?.addEventListener('click', deleteSelected);

    // --- Uploads panel ---

    const uploadedAssets = [];

    function renderUploadsGallery() {
        const gallery = document.getElementById('uploads-gallery');
        if (!gallery) return;
        gallery.innerHTML = '';
        uploadedAssets.forEach((asset) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'upload-thumb aspect-square rounded-lg overflow-hidden border border-outline-variant bg-surface-variant/30';
            btn.title = 'Add to canvas';
            btn.innerHTML = `<img src="${asset.url}" class="w-full h-full object-cover" alt="">`;
            btn.addEventListener('click', () => addDecorativeImageFromAsset(asset));
            gallery.appendChild(btn);
        });
    }

    document.getElementById('upload-image-btn')?.addEventListener('click', () => {
        document.getElementById('upload-image-input')?.click();
    });

    document.getElementById('upload-image-input')?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        await withButtonSpinner('upload-image-btn', async () => {
            const uploaded = await uploadAsset(file);
            if (!uploaded) return;
            uploadedAssets.unshift(uploaded);
            renderUploadsGallery();
            await addDecorativeImageFromAsset(uploaded);
        });
    });

    document.getElementById('undo-btn')?.addEventListener('click', undo);
    document.getElementById('redo-btn')?.addEventListener('click', redo);

    let nudgeHistoryTimer = null;

    function selectAllObjects() {
        const objects = editableObjects().filter((o) => !o.data?.locked);
        if (!objects.length) return;

        canvas.discardActiveObject();
        if (objects.length === 1) {
            canvas.setActiveObject(objects[0]);
        } else {
            const sel = new fabric.ActiveSelection(objects, { canvas });
            canvas.setActiveObject(sel);
        }
        canvas.requestRenderAll();
        refreshPropertyPanel();
        refreshLayersPanel();
    }

    function clearSelection() {
        canvas.discardActiveObject();
        canvas.requestRenderAll();
        refreshPropertyPanel();
    }

    function nudgeSelection(dx, dy) {
        const active = canvas.getActiveObject();
        if (!active || active.isEditing || active.data?.locked) return;
        if (active.data?.isGuide || active.data?.isSafeArea) return;

        active.set({
            left: (active.left ?? 0) + dx,
            top: (active.top ?? 0) + dy,
        });
        active.setCoords();
        canvas.requestRenderAll();

        // Debounce history so holding an arrow doesn't spam the undo stack.
        clearTimeout(nudgeHistoryTimer);
        nudgeHistoryTimer = setTimeout(() => pushHistory(), 350);
    }

    document.addEventListener('keydown', (event) => {
        const activeEl = document.activeElement;
        const isTypingInPage = activeEl && (
            activeEl.tagName === 'INPUT'
            || activeEl.tagName === 'TEXTAREA'
            || activeEl.tagName === 'SELECT'
            || activeEl.isContentEditable
        );
        const fabricEditing = !!canvas.getActiveObject()?.isEditing;

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z' && !event.shiftKey) {
            if (isTypingInPage || fabricEditing) return;
            event.preventDefault();
            undo();
            return;
        }
        if ((event.ctrlKey || event.metaKey) && (event.key.toLowerCase() === 'y' || (event.key.toLowerCase() === 'z' && event.shiftKey))) {
            if (isTypingInPage || fabricEditing) return;
            event.preventDefault();
            redo();
            return;
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'd') {
            if (isTypingInPage || fabricEditing) return;
            event.preventDefault();
            duplicateSelection();
            return;
        }

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
            if (isTypingInPage || fabricEditing) return;
            event.preventDefault();
            selectAllObjects();
            return;
        }

        if (event.key === 'Escape') {
            if (isTypingInPage || fabricEditing) return;
            if (isOverlayUiOpen()) return; // dedicated handlers close drawer/modal first
            clearSelection();
            return;
        }

        const arrowKeys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'];
        if (arrowKeys.includes(event.key) && !isTypingInPage && !fabricEditing) {
            const active = canvas.getActiveObject();
            if (!active) return;

            event.preventDefault();
            const step = event.shiftKey ? 10 : 1;
            const dx = event.key === 'ArrowLeft' ? -step : event.key === 'ArrowRight' ? step : 0;
            const dy = event.key === 'ArrowUp' ? -step : event.key === 'ArrowDown' ? step : 0;
            nudgeSelection(dx, dy);
            return;
        }

        if ((event.key === 'Delete' || event.key === 'Backspace') && !fabricEditing && !isTypingInPage) {
            deleteSelected();
        }
    });

    // --- Background UI ---

    function setBgStatus(text) {
        const status = document.getElementById('bg-status');
        if (!status) return;
        status.textContent = text;
        status.classList.toggle('opacity-0', !text);
    }

    document.querySelectorAll('.bg-swatch').forEach((swatch) => {
        swatch.addEventListener('click', () => {
            const color = swatch.dataset.color;
            pageBackground = { type: 'color', value: color };
            const colorInput = document.getElementById('bg-color');
            if (colorInput) colorInput.value = color;
            applyPageBackground();
            pushHistory();
        });
    });

    document.getElementById('bg-color')?.addEventListener('input', (event) => {
        pageBackground = { type: 'color', value: event.target.value };
        applyPageBackground();
        pushHistory();
    });

    document.getElementById('bg-image-btn')?.addEventListener('click', () => {
        document.getElementById('bg-image-input')?.click();
    });

    document.getElementById('bg-image-input')?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        setBgStatus('Uploading…');
        await withButtonSpinner('bg-image-btn', async () => {
            const uploaded = await uploadAsset(file);
            event.target.value = '';
            if (!uploaded) {
                setBgStatus('');
                return;
            }
            pageBackground = { type: 'image', value: uploaded.path, previewUrl: uploaded.url };
            await applyPageBackground();
            pushHistory();
        });
    });

    document.getElementById('bg-clear-btn')?.addEventListener('click', () => {
        pageBackground = { type: 'color', value: '#ffffff' };
        const colorInput = document.getElementById('bg-color');
        if (colorInput) colorInput.value = '#ffffff';
        applyPageBackground();
        pushHistory();
    });

    // --- Property panel ---

    const propEmpty = document.getElementById('property-empty');
    const propPanel = document.getElementById('property-panel');
    const textProps = document.getElementById('text-props');
    const shapeProps = document.getElementById('shape-props');
    const propFontSize = document.getElementById('prop-font-size');
    const propFontFamily = document.getElementById('prop-font-family');
    const propColor = document.getElementById('prop-color');
    const propFill = document.getElementById('prop-fill');
    const propStroke = document.getElementById('prop-stroke');
    const propStrokeWidth = document.getElementById('prop-stroke-width');
    const propTextOpacity = document.getElementById('prop-text-opacity');
    const propShapeOpacity = document.getElementById('prop-shape-opacity');
    const propCornerRadius = document.getElementById('prop-corner-radius');
    const cornerRadiusRow = document.getElementById('corner-radius-row');
    const propLetterSpacing = document.getElementById('prop-letter-spacing');
    const propLineHeight = document.getElementById('prop-line-height');
    const propShadowColor = document.getElementById('prop-shadow-color');
    const propShadowBlur = document.getElementById('prop-shadow-blur');
    const shadowControls = document.getElementById('shadow-controls');
    const propOutlineColor = document.getElementById('prop-outline-color');
    const propOutlineWidth = document.getElementById('prop-outline-width');
    const outlineControls = document.getElementById('outline-controls');
    const propHighlightColor = document.getElementById('prop-highlight-color');
    const highlightControls = document.getElementById('highlight-controls');
    const propGradientFrom = document.getElementById('prop-gradient-from');
    const propGradientTo = document.getElementById('prop-gradient-to');
    const gradientControls = document.getElementById('gradient-controls');
    const alignButtons = document.querySelectorAll('.align-btn');

    function setActiveAlignButton(align) {
        alignButtons.forEach((btn) => {
            const isActive = btn.dataset.align === align;
            btn.classList.toggle('bg-primary-fixed/60', isActive);
            btn.classList.toggle('text-primary', isActive);
            btn.classList.toggle('border-primary', isActive);
        });
    }

    function setStyleButtonActive(id, isActive) {
        const btn = document.getElementById(id);
        if (!btn) return;
        btn.classList.toggle('bg-primary-fixed/60', isActive);
        btn.classList.toggle('text-primary', isActive);
        btn.classList.toggle('border-primary', isActive);
        btn.classList.toggle('is-on', isActive);
        if (btn.hasAttribute('aria-pressed')) {
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        }
    }

    function refreshPropertyPanel() {
        const active = canvas.getActiveObject();
        const isText = active?.data?.type === 'text';
        const isShape = active?.data?.type === 'shape';
        const hasProps = isText || isShape;

        propEmpty?.classList.toggle('hidden', hasProps);
        propPanel?.classList.toggle('hidden', !hasProps);
        textProps?.classList.toggle('hidden', !isText);
        shapeProps?.classList.toggle('hidden', !isShape);

        if (isText) {
            propFontSize.value = Math.round(active.fontSize);
            propFontFamily.value = active.fontFamily || 'Inter';
            propColor.value = active.data?.textColor ?? '#151c27';
            setActiveAlignButton(active.textAlign);
            setStyleButtonActive('prop-bold', parseInt(active.fontWeight, 10) >= 600);
            setStyleButtonActive('prop-italic', active.fontStyle === 'italic');
            setStyleButtonActive('prop-underline', !!active.underline);
            setStyleButtonActive('prop-strikethrough', !!active.linethrough);
            if (propTextOpacity) propTextOpacity.value = Math.round((active.opacity ?? 1) * 100);
            if (propLetterSpacing) propLetterSpacing.value = active.charSpacing ?? 0;
            if (propLineHeight) propLineHeight.value = active.lineHeight ?? 1.2;

            const hasShadow = !!active.shadow;
            setStyleButtonActive('prop-shadow-toggle', hasShadow);
            shadowControls?.classList.toggle('hidden', !hasShadow);
            if (propShadowColor) propShadowColor.value = active.shadow?.color?.slice(0, 7) ?? '#000000';
            if (propShadowBlur) propShadowBlur.value = active.shadow?.blur ?? 4;

            const hasOutline = !!(active.stroke && (active.strokeWidth ?? 0) > 0);
            setStyleButtonActive('prop-outline-toggle', hasOutline);
            outlineControls?.classList.toggle('hidden', !hasOutline);
            if (propOutlineColor) propOutlineColor.value = hasOutline ? active.stroke : '#151c27';
            if (propOutlineWidth) propOutlineWidth.value = active.strokeWidth || 2;

            const hasHighlight = !!(active.textBackgroundColor && active.textBackgroundColor !== 'transparent');
            setStyleButtonActive('prop-highlight-toggle', hasHighlight);
            highlightControls?.classList.toggle('hidden', !hasHighlight);
            if (propHighlightColor) propHighlightColor.value = hasHighlight ? active.textBackgroundColor : '#fff59d';

            const hasGradient = !!active.data?.gradient;
            setStyleButtonActive('prop-gradient-toggle', hasGradient);
            gradientControls?.classList.toggle('hidden', !hasGradient);
            if (propGradientFrom) propGradientFrom.value = active.data?.gradient?.from ?? '#b40012';
            if (propGradientTo) propGradientTo.value = active.data?.gradient?.to ?? '#f59e0b';

            setStyleButtonActive('prop-autofit-toggle', !!active.data?.autoFit);
        }

        if (isShape) {
            const fill = typeof active.fill === 'string' && active.fill.startsWith('#') ? active.fill : '#ffffff';
            const stroke = typeof active.stroke === 'string' && active.stroke.startsWith('#') ? active.stroke : '#151c27';
            propFill.value = fill;
            propStroke.value = stroke;
            propStrokeWidth.value = active.strokeWidth ?? 2;
            if (propShapeOpacity) propShapeOpacity.value = Math.round((active.opacity ?? 1) * 100);
            setStyleButtonActive('prop-flip-h', !!active.flipX);
            setStyleButtonActive('prop-flip-v', !!active.flipY);

            const isRect = active.data?.shapeKind === 'rect';
            cornerRadiusRow?.classList.toggle('hidden', !isRect);
            if (isRect && propCornerRadius) propCornerRadius.value = active.rx ?? 0;
        }
    }

    canvas.on('selection:created', refreshPropertyPanel);
    canvas.on('selection:updated', refreshPropertyPanel);
    canvas.on('selection:cleared', refreshPropertyPanel);

    propFontSize?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'text') {
            active.set('fontSize', Number(event.target.value));
            canvas.requestRenderAll();
        }
    });
    propFontFamily?.addEventListener('change', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'text') {
            active.set('fontFamily', event.target.value);
            canvas.requestRenderAll();
            pushHistory();
        }
    });
    propColor?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'text') {
            active.data.textColor = event.target.value;
            // A gradient (when on) overrides the solid fill — the picker
            // just keeps the fallback color current for when it's toggled off.
            if (!active.data.gradient) {
                active.set('fill', event.target.value);
            }
            canvas.requestRenderAll();
        }
    });
    alignButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const active = canvas.getActiveObject();
            if (active?.data?.type === 'text') {
                active.set('textAlign', btn.dataset.align);
                canvas.requestRenderAll();
                setActiveAlignButton(btn.dataset.align);
                pushHistory();
            }
        });
    });
    propFill?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'shape') {
            active.set('fill', event.target.value);
            canvas.requestRenderAll();
        }
    });
    propStroke?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'shape') {
            active.set('stroke', event.target.value);
            canvas.requestRenderAll();
        }
    });
    propStrokeWidth?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'shape') {
            active.set('strokeWidth', Number(event.target.value));
            canvas.requestRenderAll();
        }
    });

    // --- Text style toggles (bold / italic / underline / opacity) ---

    document.getElementById('prop-bold')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        const isBold = parseInt(active.fontWeight, 10) >= 600;
        active.set('fontWeight', isBold ? '400' : '700');
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    document.getElementById('prop-italic')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        active.set('fontStyle', active.fontStyle === 'italic' ? 'normal' : 'italic');
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    document.getElementById('prop-underline')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        active.set('underline', !active.underline);
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    document.getElementById('prop-strikethrough')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        active.set('linethrough', !active.linethrough);
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    propTextOpacity?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'text') {
            active.set('opacity', Number(event.target.value) / 100);
            canvas.requestRenderAll();
        }
    });
    propTextOpacity?.addEventListener('change', () => pushHistory());

    // --- Change case (one-shot, mutates the literal text like a word processor's "change case") ---

    function titleCase(text) {
        return text.replace(/\w\S*/g, (word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase());
    }

    function applyCaseTransform(transformFn) {
        const active = canvas.getActiveObject();
        // Bound fields (recipient name, dates, etc.) pull live content from
        // the certificate at issue time — there's no static text here to
        // rewrite, so the buttons are a no-op for them.
        if (active?.data?.type !== 'text' || active.data?.binding) return;
        active.set('text', transformFn(active.text ?? ''));
        canvas.requestRenderAll();
        pushHistory();
    }

    document.getElementById('prop-case-title')?.addEventListener('click', () => applyCaseTransform(titleCase));
    document.getElementById('prop-case-upper')?.addEventListener('click', () => applyCaseTransform((t) => t.toUpperCase()));
    document.getElementById('prop-case-lower')?.addEventListener('click', () => applyCaseTransform((t) => t.toLowerCase()));

    // --- Letter spacing / line height ---

    propLetterSpacing?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'text') {
            active.set('charSpacing', Number(event.target.value));
            canvas.requestRenderAll();
        }
    });
    propLetterSpacing?.addEventListener('change', () => pushHistory());
    propLineHeight?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'text') {
            active.set('lineHeight', Number(event.target.value));
            canvas.requestRenderAll();
        }
    });
    propLineHeight?.addEventListener('change', () => pushHistory());

    // --- Text effects: shadow / outline / highlight / gradient / auto-fit ---

    document.getElementById('prop-shadow-toggle')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        active.set('shadow', active.shadow ? null : new fabric.Shadow({
            color: propShadowColor?.value ?? '#000000',
            blur: Number(propShadowBlur?.value ?? 4),
            offsetX: 2,
            offsetY: 2,
        }));
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    function updateShadow() {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text' || !active.shadow) return;
        active.set('shadow', new fabric.Shadow({
            color: propShadowColor?.value ?? '#000000',
            blur: Number(propShadowBlur?.value ?? 4),
            offsetX: 2,
            offsetY: 2,
        }));
        canvas.requestRenderAll();
    }
    propShadowColor?.addEventListener('input', updateShadow);
    propShadowColor?.addEventListener('change', () => pushHistory());
    propShadowBlur?.addEventListener('input', updateShadow);
    propShadowBlur?.addEventListener('change', () => pushHistory());

    document.getElementById('prop-outline-toggle')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        const enabling = !(active.stroke && (active.strokeWidth ?? 0) > 0);
        active.set({
            stroke: enabling ? (propOutlineColor?.value ?? '#151c27') : '',
            strokeWidth: enabling ? Number(propOutlineWidth?.value ?? 2) : 0,
            paintFirst: 'stroke',
        });
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    function updateOutline() {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text' || !active.stroke) return;
        active.set({
            stroke: propOutlineColor?.value ?? '#151c27',
            strokeWidth: Number(propOutlineWidth?.value ?? 2),
        });
        canvas.requestRenderAll();
    }
    propOutlineColor?.addEventListener('input', updateOutline);
    propOutlineColor?.addEventListener('change', () => pushHistory());
    propOutlineWidth?.addEventListener('input', updateOutline);
    propOutlineWidth?.addEventListener('change', () => pushHistory());

    document.getElementById('prop-highlight-toggle')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        const enabling = !(active.textBackgroundColor && active.textBackgroundColor !== 'transparent');
        active.set('textBackgroundColor', enabling ? (propHighlightColor?.value ?? '#fff59d') : '');
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    propHighlightColor?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'text' && active.textBackgroundColor) {
            active.set('textBackgroundColor', event.target.value);
            canvas.requestRenderAll();
        }
    });
    propHighlightColor?.addEventListener('change', () => pushHistory());

    document.getElementById('prop-gradient-toggle')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        if (active.data.gradient) {
            active.data.gradient = null;
            active.set('fill', active.data.textColor ?? '#151c27');
        } else {
            active.data.gradient = {
                from: propGradientFrom?.value ?? '#b40012',
                to: propGradientTo?.value ?? '#f59e0b',
            };
            active.set('fill', buildTextGradient(active.data.gradient));
        }
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    function updateGradient() {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text' || !active.data?.gradient) return;
        active.data.gradient = {
            from: propGradientFrom?.value ?? '#b40012',
            to: propGradientTo?.value ?? '#f59e0b',
        };
        active.set('fill', buildTextGradient(active.data.gradient));
        canvas.requestRenderAll();
    }
    propGradientFrom?.addEventListener('input', updateGradient);
    propGradientFrom?.addEventListener('change', () => pushHistory());
    propGradientTo?.addEventListener('input', updateGradient);
    propGradientTo?.addEventListener('change', () => pushHistory());

    document.getElementById('prop-autofit-toggle')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'text') return;
        active.data.autoFit = !active.data.autoFit;
        refreshPropertyPanel();
        pushHistory();
    });

    // --- Shape customization (corner radius / opacity / flip) ---

    propCornerRadius?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'shape' && active.data?.shapeKind === 'rect') {
            const radius = Number(event.target.value);
            active.set({ rx: radius, ry: radius });
            canvas.requestRenderAll();
        }
    });
    propCornerRadius?.addEventListener('change', () => pushHistory());
    propShapeOpacity?.addEventListener('input', (event) => {
        const active = canvas.getActiveObject();
        if (active?.data?.type === 'shape') {
            active.set('opacity', Number(event.target.value) / 100);
            canvas.requestRenderAll();
        }
    });
    propShapeOpacity?.addEventListener('change', () => pushHistory());
    document.getElementById('prop-flip-h')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'shape') return;
        active.set('flipX', !active.flipX);
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });
    document.getElementById('prop-flip-v')?.addEventListener('click', () => {
        const active = canvas.getActiveObject();
        if (active?.data?.type !== 'shape') return;
        active.set('flipY', !active.flipY);
        canvas.requestRenderAll();
        refreshPropertyPanel();
        pushHistory();
    });

    // --- Duplicate / lock / align / safe-area / autosave / presets ---

    function editableObjects() {
        return canvas.getObjects().filter((o) => !o.data?.isGuide && !o.data?.isSafeArea);
    }

    function selectionTargets() {
        const active = canvas.getActiveObject();
        if (!active) return [];
        if (active.type === 'activeselection' || active.type === 'activeSelection') {
            return active.getObjects().filter((o) => !o.data?.locked);
        }
        return active.data?.locked ? [] : [active];
    }

    function applyLockState(obj, locked) {
        if (!obj.data) obj.data = {};
        obj.data.locked = !!locked;
        obj.set({
            selectable: !locked,
            evented: !locked,
            lockMovementX: locked,
            lockMovementY: locked,
            lockScalingX: locked,
            lockScalingY: locked,
            lockRotation: locked,
        });
        if (locked && canvas.getActiveObject() === obj) {
            canvas.discardActiveObject();
        }
    }

    async function duplicateSelection() {
        const targets = selectionTargets();
        if (!targets.length) return;

        const clones = [];
        for (const obj of targets) {
            // eslint-disable-next-line no-await-in-loop
            const clone = await obj.clone();
            clone.set({
                left: (obj.left ?? 0) + 12,
                top: (obj.top ?? 0) + 12,
            });
            clone.data = {
                ...(obj.data ? { ...obj.data } : {}),
                id: generateId(),
                locked: false,
            };
            applyLockState(clone, false);
            canvas.add(clone);
            clones.push(clone);
        }

        if (clones.length === 1) {
            canvas.setActiveObject(clones[0]);
        } else if (clones.length > 1) {
            const sel = new fabric.ActiveSelection(clones, { canvas });
            canvas.setActiveObject(sel);
        }
        canvas.requestRenderAll();
        pushHistory();
        refreshLayersPanel();
        refreshEmptyHint();
        markDirty();
    }

    function toggleLockSelection() {
        const active = canvas.getActiveObject();
        if (!active) return;
        const targets = (active.type === 'activeselection' || active.type === 'activeSelection')
            ? active.getObjects()
            : [active];
        const shouldLock = targets.some((o) => !o.data?.locked);
        targets.forEach((o) => applyLockState(o, shouldLock));
        canvas.requestRenderAll();
        pushHistory();
        refreshLayersPanel();
        markDirty();
    }

    function alignObjects(mode) {
        const targets = selectionTargets();
        if (!targets.length) return;

        const useCanvas = targets.length === 1;
        const bounds = useCanvas
            ? { left: 0, top: 0, width: canvasSize.width, height: canvasSize.height }
            : (() => {
                let minL = Infinity;
                let minT = Infinity;
                let maxR = -Infinity;
                let maxB = -Infinity;
                targets.forEach((o) => {
                    minL = Math.min(minL, o.left);
                    minT = Math.min(minT, o.top);
                    maxR = Math.max(maxR, o.left + o.getScaledWidth());
                    maxB = Math.max(maxB, o.top + o.getScaledHeight());
                });
                return { left: minL, top: minT, width: maxR - minL, height: maxB - minT };
            })();

        targets.forEach((o) => {
            const w = o.getScaledWidth();
            const h = o.getScaledHeight();
            if (mode === 'left') o.set('left', bounds.left);
            if (mode === 'center') o.set('left', bounds.left + (bounds.width - w) / 2);
            if (mode === 'right') o.set('left', bounds.left + bounds.width - w);
            if (mode === 'top') o.set('top', bounds.top);
            if (mode === 'middle') o.set('top', bounds.top + (bounds.height - h) / 2);
            if (mode === 'bottom') o.set('top', bounds.top + bounds.height - h);
            o.setCoords();
        });
        canvas.requestRenderAll();
        pushHistory();
        markDirty();
    }

    function distributeObjects(axis) {
        const targets = selectionTargets().slice().sort((a, b) => (
            axis === 'horizontal' ? a.left - b.left : a.top - b.top
        ));
        if (targets.length < 3) return;

        if (axis === 'horizontal') {
            const first = targets[0].left;
            const last = targets[targets.length - 1].left + targets[targets.length - 1].getScaledWidth();
            const totalW = targets.reduce((sum, o) => sum + o.getScaledWidth(), 0);
            const gap = (last - first - totalW) / (targets.length - 1);
            let cursor = first;
            targets.forEach((o) => {
                o.set('left', cursor);
                cursor += o.getScaledWidth() + gap;
                o.setCoords();
            });
        } else {
            const first = targets[0].top;
            const last = targets[targets.length - 1].top + targets[targets.length - 1].getScaledHeight();
            const totalH = targets.reduce((sum, o) => sum + o.getScaledHeight(), 0);
            const gap = (last - first - totalH) / (targets.length - 1);
            let cursor = first;
            targets.forEach((o) => {
                o.set('top', cursor);
                cursor += o.getScaledHeight() + gap;
                o.setCoords();
            });
        }
        canvas.requestRenderAll();
        pushHistory();
        markDirty();
    }

    let safeAreaVisible = false;
    let safeAreaRect = null;

    function toggleSafeArea() {
        safeAreaVisible = !safeAreaVisible;
        if (safeAreaRect) {
            canvas.remove(safeAreaRect);
            safeAreaRect = null;
        }
        if (safeAreaVisible) {
            const insetX = canvasSize.width * 0.05;
            const insetY = canvasSize.height * 0.05;
            safeAreaRect = new fabric.Rect({
                left: insetX,
                top: insetY,
                width: canvasSize.width - insetX * 2,
                height: canvasSize.height - insetY * 2,
                fill: 'transparent',
                stroke: '#03757f',
                strokeDashArray: [8, 6],
                strokeWidth: 1,
                selectable: false,
                evented: false,
                opacity: 0.7,
            });
            safeAreaRect.data = { isSafeArea: true, isGuide: true };
            canvas.add(safeAreaRect);
            canvas.sendObjectToBack(safeAreaRect);
        }
        document.getElementById('safe-area-toggle')?.classList.toggle('bg-primary-fixed/60', safeAreaVisible);
        document.getElementById('safe-area-toggle')?.classList.toggle('text-primary', safeAreaVisible);
        canvas.requestRenderAll();
    }

    let dirty = false;
    let autosaveTimer = null;
    let lastSavedAt = config.publishedAt ? new Date(config.publishedAt) : null;
    let savedAgoTimer = null;

    function markDirty() {
        dirty = true;
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(() => autosaveDraft(), 2000);
    }

    function formatSavedAgo() {
        if (!lastSavedAt) return '';
        const seconds = Math.max(0, Math.round((Date.now() - lastSavedAt.getTime()) / 1000));
        if (seconds < 5) return 'Saved just now';
        if (seconds < 60) return `Saved ${seconds}s ago`;
        return `Saved ${Math.floor(seconds / 60)}m ago`;
    }

    function tickSavedAgo() {
        if (!dirty && lastSavedAt) {
            setStatus(formatSavedAgo());
        }
    }

    async function autosaveDraft() {
        if (!dirty) return;
        setStatus('Saving…');
        try {
            await persist(config.saveUrl);
            dirty = false;
            lastSavedAt = new Date();
            setStatus(formatSavedAgo());
        } catch (error) {
            setStatus('Autosave failed');
        }
    }

    function clearCanvasForPreset() {
        editableObjects().forEach((o) => canvas.remove(o));
    }

    /** Square box sized as a % of canvas width (keeps QR/logo equal). */
    function squarePercents(sizeOfWidth) {
        const heightPercent = (sizeOfWidth / 100 * canvasSize.width) / canvasSize.height * 100;
        return { widthPercent: sizeOfWidth, heightPercent };
    }

    function layoutPreset(name) {
        const margin = 6;
        const mark = squarePercents(9);
        const bottomY = Math.max(margin, 100 - margin - mark.heightPercent);
        const topY = margin;
        const rightX = 100 - margin - mark.widthPercent;
        const centerX = (100 - mark.widthPercent) / 2;
        const sigW = 22;
        const sigH = mark.heightPercent;
        const sigX = 100 - margin - sigW;
        const sigY = bottomY;

        const presets = {
            award: {
                background: { type: 'color', value: '#ffffff' },
                elements: [
                    { id: 'p1', type: 'text', binding: null, content: 'CERTIFICATE', xPercent: 18, yPercent: 18, widthPercent: 64, heightPercent: 8, rotation: 0, z: 0, style: { fontFamily: 'Montserrat', fontSize: 36, fontWeight: '700', color: '#03757f', textAlign: 'center' } },
                    { id: 'p2', type: 'shape', shapeKind: 'rect', xPercent: 22, yPercent: 28, widthPercent: 56, heightPercent: 7, rotation: 0, z: 1, style: { fill: '#fdcf48', stroke: '#fdcf48', strokeWidth: 0 } },
                    { id: 'p3', type: 'text', binding: 'title', xPercent: 22, yPercent: 29, widthPercent: 56, heightPercent: 5, rotation: 0, z: 2, style: { fontFamily: 'Montserrat', fontSize: 22, fontWeight: '700', color: '#ffffff', textAlign: 'center' } },
                    { id: 'p4', type: 'text', binding: null, content: 'THIS CERTIFICATE IS PROUDLY PRESENTED TO', xPercent: 15, yPercent: 40, widthPercent: 70, heightPercent: 4, rotation: 0, z: 3, style: { fontFamily: 'Inter', fontSize: 14, fontWeight: '600', color: '#03757f', textAlign: 'center' } },
                    { id: 'p5', type: 'text', binding: 'recipient_name', xPercent: 15, yPercent: 46, widthPercent: 70, heightPercent: 8, rotation: 0, z: 4, style: { fontFamily: 'Playfair Display', fontSize: 32, fontWeight: '700', color: '#151c27', textAlign: 'center' } },
                    { id: 'p6', type: 'shape', shapeKind: 'line', xPercent: 22, yPercent: 56, widthPercent: 56, heightPercent: 0.4, rotation: 0, z: 5, style: { fill: '#151c27', stroke: '#151c27', strokeWidth: 0 } },
                    { id: 'p7', type: 'text', binding: 'description', xPercent: 18, yPercent: 58, widthPercent: 64, heightPercent: 8, rotation: 0, z: 6, style: { fontFamily: 'Inter', fontSize: 14, fontWeight: '400', color: '#151c27', textAlign: 'center' } },
                    { id: 'p8', type: 'qrcode', binding: 'qrcode', xPercent: margin, yPercent: topY, ...mark, rotation: 0, z: 7 },
                    { id: 'p9', type: 'company_logo', binding: 'company_logo', xPercent: rightX, yPercent: topY, ...mark, rotation: 0, z: 8 },
                    { id: 'p10', type: 'shape', shapeKind: 'seal', xPercent: margin, yPercent: bottomY, widthPercent: mark.widthPercent + 2, heightPercent: mark.heightPercent, rotation: 0, z: 9, style: { fill: '#fdcf48', stroke: '#03757f', strokeWidth: 2 } },
                    { id: 'p11', type: 'signature', binding: 'signature', xPercent: sigX, yPercent: sigY, widthPercent: sigW, heightPercent: sigH, rotation: 0, z: 10 },
                    { id: 'p12', type: 'text', binding: 'date_of_issue', xPercent: sigX, yPercent: Math.min(96, sigY + sigH + 1), widthPercent: sigW, heightPercent: 4, rotation: 0, z: 11, style: { fontFamily: 'Inter', fontSize: 12, fontWeight: '400', color: '#151c27', textAlign: 'center' } },
                ],
            },
            course: {
                background: { type: 'color', value: '#fff8f0' },
                elements: [
                    { id: 'c1', type: 'text', binding: null, content: 'CERTIFICATE OF COMPLETION', xPercent: 10, yPercent: 14, widthPercent: 80, heightPercent: 8, rotation: 0, z: 0, style: { fontFamily: 'Cinzel', fontSize: 28, fontWeight: '700', color: '#151c27', textAlign: 'center' } },
                    { id: 'c2', type: 'text', binding: 'title', xPercent: 15, yPercent: 26, widthPercent: 70, heightPercent: 6, rotation: 0, z: 1, style: { fontFamily: 'Montserrat', fontSize: 20, fontWeight: '600', color: '#b40012', textAlign: 'center' } },
                    { id: 'c3', type: 'text', binding: 'recipient_name', xPercent: 15, yPercent: 38, widthPercent: 70, heightPercent: 8, rotation: 0, z: 2, style: { fontFamily: 'Great Vibes', fontSize: 40, fontWeight: '400', color: '#151c27', textAlign: 'center' } },
                    { id: 'c4', type: 'text', binding: 'description', xPercent: 18, yPercent: 52, widthPercent: 64, heightPercent: 10, rotation: 0, z: 3, style: { fontFamily: 'Lora', fontSize: 14, fontWeight: '400', color: '#334155', textAlign: 'center' } },
                    { id: 'c5', type: 'qrcode', binding: 'qrcode', xPercent: margin, yPercent: bottomY, ...mark, rotation: 0, z: 4 },
                    { id: 'c6', type: 'company_logo', binding: 'company_logo', xPercent: centerX, yPercent: bottomY, ...mark, rotation: 0, z: 5 },
                    { id: 'c7', type: 'signature', binding: 'signature', xPercent: sigX, yPercent: bottomY, widthPercent: sigW, heightPercent: sigH, rotation: 0, z: 6 },
                ],
            },
            internship: {
                background: { type: 'color', value: '#ffffff' },
                elements: [
                    { id: 'i1', type: 'company_logo', binding: 'company_logo', xPercent: centerX, yPercent: topY, ...mark, rotation: 0, z: 0 },
                    { id: 'i2', type: 'qrcode', binding: 'qrcode', xPercent: margin, yPercent: topY, ...mark, rotation: 0, z: 1 },
                    { id: 'i3', type: 'text', binding: null, content: 'INTERNSHIP CERTIFICATE', xPercent: 10, yPercent: 28, widthPercent: 80, heightPercent: 7, rotation: 0, z: 2, style: { fontFamily: 'Montserrat', fontSize: 26, fontWeight: '700', color: '#0f172a', textAlign: 'center' } },
                    { id: 'i4', type: 'text', binding: 'recipient_name', xPercent: 15, yPercent: 40, widthPercent: 70, heightPercent: 8, rotation: 0, z: 3, style: { fontFamily: 'Playfair Display', fontSize: 30, fontWeight: '700', color: '#151c27', textAlign: 'center' } },
                    { id: 'i5', type: 'text', binding: 'description', xPercent: 15, yPercent: 52, widthPercent: 70, heightPercent: 10, rotation: 0, z: 4, style: { fontFamily: 'Inter', fontSize: 14, fontWeight: '400', color: '#475569', textAlign: 'center' } },
                    { id: 'i6', type: 'text', binding: 'date_of_issue', xPercent: margin, yPercent: bottomY + (sigH - 5) / 2, widthPercent: 28, heightPercent: 5, rotation: 0, z: 5, style: { fontFamily: 'Inter', fontSize: 13, fontWeight: '500', color: '#151c27', textAlign: 'left' } },
                    { id: 'i7', type: 'signature', binding: 'signature', xPercent: sigX, yPercent: bottomY, widthPercent: sigW, heightPercent: sigH, rotation: 0, z: 6 },
                ],
            },
        };

        return presets[name] ?? null;
    }

    const PRESET_LABELS = {
        award: 'Award',
        course: 'Course',
        internship: 'Internship',
    };

    let pendingPresetName = null;

    function openPresetConfirm(name) {
        pendingPresetName = name;
        const modal = document.getElementById('preset-confirm-modal');
        const label = document.getElementById('preset-confirm-name');
        if (label) label.textContent = PRESET_LABELS[name] || name;
        modal?.classList.remove('hidden');
    }

    function closePresetConfirm() {
        pendingPresetName = null;
        document.getElementById('preset-confirm-modal')?.classList.add('hidden');
    }

    function commitPreset(name) {
        const preset = layoutPreset(name);
        if (!preset) return;
        historySuspended = true;
        clearCanvasForPreset();
        pageBackground = preset.background ?? { type: 'color', value: '#ffffff' };
        applyPageBackground().then(() => {
            deserialize(preset);
            historySuspended = false;
            pushHistory();
            refreshEmptyHint();
            markDirty();
        });
    }

    function applyPreset(name) {
        const preset = layoutPreset(name);
        if (!preset) return;
        if (editableObjects().length > 0) {
            openPresetConfirm(name);
            return;
        }
        commitPreset(name);
    }

    /**
     * Upload / library decorative images. Do NOT set crossOrigin on same-
     * origin /storage URLs — that blanks SVGs and many local assets when
     * Apache doesn't emit ACAO headers. SVGs are parsed into Fabric objects
     * when possible so viewBox-only seeds still render.
     */
    async function loadDecorativeFabricImage(url) {
        if (/\.svg(\?|#|$)/i.test(url)) {
            try {
                const parsed = await fabric.loadSVGFromURL(url);
                const objects = (parsed.objects || []).filter(Boolean);
                if (objects.length) {
                    const grouped = fabric.util.groupSVGElements(objects, parsed.options || {});
                    // Rasterize to a FabricImage so serialize/restore stay on
                    // the Image path (src on disk) and canvas PDF can loadImage.
                    const bounds = grouped.getBoundingRect();
                    const exportSize = Math.max(2, Math.ceil(Math.max(bounds.width, bounds.height)));
                    const dataUrl = grouped.toDataURL({
                        format: 'png',
                        multiplier: Math.min(4, 256 / exportSize),
                        enableRetinaScaling: false,
                    });
                    return fabric.FabricImage.fromURL(dataUrl);
                }
            } catch (error) {
                console.warn('SVG parse failed, falling back to image load', error);
            }
        }

        const img = await fabric.FabricImage.fromURL(url);
        if (!img?.width || !img?.height) {
            throw new Error('Image failed to load or has zero size');
        }

        return img;
    }

    function placeDecorativeImage(img, path, url, box = null) {
        if (box) {
            img.set({ left: box.left, top: box.top, angle: box.angle ?? 0, originX: 'left', originY: 'top' });
            img.scaleToWidth(box.width);
            if (img.getScaledHeight() > box.height) {
                img.scaleToHeight(box.height);
            }
            img.data = {
                id: box.id ?? generateId(),
                type: 'image',
                binding: null,
                src: path,
                previewUrl: url,
                label: 'Image',
            };
        } else {
            const maxW = canvasSize.width * 0.25;
            img.scaleToWidth(Math.min(maxW, img.width || maxW));
            img.set({
                left: canvasSize.width / 2 - img.getScaledWidth() / 2,
                top: canvasSize.height / 2 - img.getScaledHeight() / 2,
                originX: 'left',
                originY: 'top',
            });
            img.data = {
                id: generateId(),
                type: 'image',
                binding: null,
                src: path,
                previewUrl: url,
                label: 'Image',
            };
        }

        canvas.add(img);
        canvas.setActiveObject(img);
        canvas.requestRenderAll();
        pushHistory();
        refreshLayersPanel();
        refreshEmptyHint();
        markDirty();
    }

    async function insertLibraryAsset(path, url) {
        const active = canvas.getActiveObject();
        let box = null;

        if (active?.data?.type === 'image' && !active.data?.binding && active.type !== 'group') {
            box = {
                left: active.left,
                top: active.top,
                width: active.getScaledWidth(),
                height: active.getScaledHeight(),
                angle: active.angle ?? 0,
                id: active.data?.id ?? generateId(),
            };
            canvas.remove(active);
        }

        try {
            const img = await loadDecorativeFabricImage(url);
            placeDecorativeImage(img, path, url, box);
        } catch (error) {
            console.error('Library insert failed', error);
            showBuilderToast('Could not add that library image. Try a PNG/JPG, or re-upload the asset.', 'error');
        }
    }

    async function loadLibraryGallery() {
        const gallery = document.getElementById('library-gallery');
        if (!gallery || !config.libraryListUrl) return;
        try {
            const response = await fetch(config.libraryListUrl, {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrfToken },
            });
            if (!response.ok) return;
            const assets = await response.json();
            gallery.innerHTML = '';
            assets.forEach((asset) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'upload-thumb aspect-square rounded-lg overflow-hidden border border-outline-variant bg-surface-variant/40';
                btn.title = asset.name ?? 'Asset';
                btn.innerHTML = `<img src="${asset.url}" alt="" class="w-full h-full object-contain">`;
                btn.addEventListener('click', () => insertLibraryAsset(asset.path, asset.url));
                gallery.appendChild(btn);
            });
        } catch (error) {
            console.error('Library load failed', error);
        }
    }

    function setPreviewDrawerOpen(open) {
        const drawer = document.getElementById('sample-preview-drawer');
        const btn = document.getElementById('preview-sample-btn');
        drawer?.classList.toggle('hidden', !open);
        btn?.classList.toggle('bg-primary-fixed/60', open);
        btn?.classList.toggle('text-primary', open);
    }

    function closeSamplePreview() {
        setPreviewDrawerOpen(false);
    }

    async function openSamplePreview() {
        const drawer = document.getElementById('sample-preview-drawer');
        const loading = document.getElementById('sample-preview-loading');
        const image = document.getElementById('sample-preview-image');
        const frame = document.getElementById('sample-preview-frame');
        const errorEl = document.getElementById('sample-preview-error');
        if (!drawer || !config.previewSampleUrl) return;

        setPreviewDrawerOpen(true);
        loading?.classList.remove('hidden');
        errorEl?.classList.add('hidden');
        if (errorEl) errorEl.textContent = '';
        image?.classList.add('hidden');
        frame?.classList.add('hidden');

        try {
            // Persist draft first so preview reads latest canvas_json.
            await persist(config.saveUrl);
            dirty = false;
            lastSavedAt = new Date();
            setStatus(formatSavedAgo());

            const response = await fetch(config.previewSampleUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': config.csrfToken,
                    Accept: 'image/png, text/html',
                },
            });

            loading?.classList.add('hidden');
            if (!response.ok) {
                if (errorEl) {
                    errorEl.textContent = 'Preview failed. Save once and try Refresh.';
                    errorEl.classList.remove('hidden');
                }
                return;
            }

            const mode = response.headers.get('X-Preview-Mode') || 'html';
            if (mode === 'canvas' && image) {
                const blob = await response.blob();
                if (image.dataset.blobUrl) {
                    URL.revokeObjectURL(image.dataset.blobUrl);
                }
                const blobUrl = URL.createObjectURL(blob);
                image.dataset.blobUrl = blobUrl;
                image.src = blobUrl;
                image.classList.remove('hidden');
            } else if (frame) {
                frame.srcdoc = await response.text();
                frame.classList.remove('hidden');
            }
        } catch (error) {
            loading?.classList.add('hidden');
            if (errorEl) {
                errorEl.textContent = 'Preview failed. Check your connection and try again.';
                errorEl.classList.remove('hidden');
            }
        }
    }

    // --- Save / Publish ---

    const statusLabel = document.getElementById('builder-status');

    function setStatus(text) {
        if (!statusLabel) return;
        statusLabel.textContent = text;
        statusLabel.classList.toggle('opacity-0', !text);
    }

    document.getElementById('save-draft-btn')?.addEventListener('click', async () => {
        setStatus('Saving…');
        await persist(config.saveUrl);
        dirty = false;
        lastSavedAt = new Date();
        setStatus(formatSavedAgo());
    });

    document.getElementById('publish-btn')?.addEventListener('click', async () => {
        if (editableObjects().length === 0) {
            showBuilderToast('Add at least one element before publishing.', 'warn');
            return;
        }
        setStatus('Publishing…');
        const result = await persist(config.publishUrl);
        if (result.redirect) window.location.href = result.redirect;
    });

    document.getElementById('shortcuts-help-btn')?.addEventListener('click', (event) => {
        event.stopPropagation();
        const panel = document.getElementById('shortcuts-help-panel');
        const btn = document.getElementById('shortcuts-help-btn');
        const open = panel?.classList.toggle('hidden') === false;
        btn?.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', (event) => {
        const panel = document.getElementById('shortcuts-help-panel');
        const btn = document.getElementById('shortcuts-help-btn');
        if (!panel || panel.classList.contains('hidden')) return;
        if (btn?.contains(event.target) || panel.contains(event.target)) return;
        panel.classList.add('hidden');
        btn?.setAttribute('aria-expanded', 'false');
    });

    document.getElementById('duplicate-btn')?.addEventListener('click', () => duplicateSelection());
    document.getElementById('lock-btn')?.addEventListener('click', () => toggleLockSelection());
    document.getElementById('safe-area-toggle')?.addEventListener('click', () => toggleSafeArea());
    document.getElementById('preview-sample-btn')?.addEventListener('click', () => {
        const drawer = document.getElementById('sample-preview-drawer');
        if (drawer && !drawer.classList.contains('hidden')) {
            closeSamplePreview();
            return;
        }
        openSamplePreview();
    });
    document.getElementById('sample-preview-close')?.addEventListener('click', () => closeSamplePreview());
    document.getElementById('sample-preview-refresh')?.addEventListener('click', () => openSamplePreview());
    document.getElementById('preset-confirm-cancel')?.addEventListener('click', () => closePresetConfirm());
    document.getElementById('preset-confirm-apply')?.addEventListener('click', () => {
        const name = pendingPresetName;
        closePresetConfirm();
        if (name) commitPreset(name);
    });
    document.getElementById('preset-confirm-modal')?.addEventListener('click', (event) => {
        if (event.target.id === 'preset-confirm-modal') closePresetConfirm();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (!document.getElementById('preset-confirm-modal')?.classList.contains('hidden')) {
            closePresetConfirm();
            return;
        }
        if (!document.getElementById('sample-preview-drawer')?.classList.contains('hidden')) {
            closeSamplePreview();
        }
    });
    document.querySelectorAll('[data-object-align]').forEach((btn) => {
        btn.addEventListener('click', () => alignObjects(btn.dataset.objectAlign));
    });
    document.querySelectorAll('[data-object-distribute]').forEach((btn) => {
        btn.addEventListener('click', () => distributeObjects(btn.dataset.objectDistribute));
    });
    document.querySelectorAll('.preset-btn').forEach((btn) => {
        btn.addEventListener('click', () => applyPreset(btn.dataset.preset));
    });
    document.getElementById('empty-add-title')?.addEventListener('click', () => {
        addText({ binding: 'title', content: '', label: 'Certificate Title' });
    });
    document.getElementById('empty-add-recipient')?.addEventListener('click', () => {
        addText({ binding: 'recipient_name', content: '', label: 'Recipient Name' });
    });
    document.getElementById('empty-add-logo')?.addEventListener('click', () => {
        addPlaceholder('company_logo', 'COMPANY LOGO');
    });
    document.getElementById('library-upload-btn')?.addEventListener('click', () => {
        document.getElementById('library-upload-input')?.click();
    });
    document.getElementById('library-upload-input')?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file || !config.libraryUploadUrl) return;
        await withButtonSpinner('library-upload-btn', async () => {
            const body = new FormData();
            body.append('file', file);
            const response = await fetch(config.libraryUploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json' },
                body,
            });
            if (!response.ok) {
                showBuilderToast('Library upload failed.', 'error');
                return;
            }
            await loadLibraryGallery();
        });
    });
    document.getElementById('replace-image-btn')?.addEventListener('click', () => {
        document.getElementById('replace-image-input')?.click();
    });
    document.getElementById('replace-image-input')?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        await withButtonSpinner('replace-image-btn', async () => {
            const uploaded = await uploadAsset(file);
            if (!uploaded) return;
            await insertLibraryAsset(uploaded.path, uploaded.url);
        });
    });

    // Keep replace button visibility in sync with selection.
    canvas.on('selection:created', () => {
        const active = canvas.getActiveObject();
        const replaceBtn = document.getElementById('replace-image-btn');
        if (replaceBtn) {
            replaceBtn.classList.toggle('hidden', !(active?.data?.type === 'image' && !active.data?.binding && active.type !== 'group'));
        }
    });
    canvas.on('selection:updated', () => {
        const active = canvas.getActiveObject();
        const replaceBtn = document.getElementById('replace-image-btn');
        if (replaceBtn) {
            replaceBtn.classList.toggle('hidden', !(active?.data?.type === 'image' && !active.data?.binding && active.type !== 'group'));
        }
    });
    canvas.on('selection:cleared', () => {
        document.getElementById('replace-image-btn')?.classList.add('hidden');
    });

    savedAgoTimer = setInterval(tickSavedAgo, 5000);
    loadLibraryGallery();

    // --- Zoom / fit ---

    let zoom = 1;
    const zoomLabel = document.getElementById('zoom-fit') || document.getElementById('zoom-level');
    const canvasStage = document.getElementById('canvas-stage') || document.getElementById('canvas-wrapper');
    const workspace = document.getElementById('builder-workspace');

    function applyZoom() {
        if (canvasStage) canvasStage.style.transform = `scale(${zoom})`;
        if (zoomLabel) zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
    }

    function fitToWorkspace() {
        if (!workspace) {
            zoom = 1;
            applyZoom();
            return;
        }
        const pad = 48;
        const availW = Math.max(200, workspace.clientWidth - pad);
        const availH = Math.max(200, workspace.clientHeight - pad);
        zoom = Math.min(1, availW / canvasSize.width, availH / canvasSize.height);
        // Floor so we don't go unreadably tiny, but show full page.
        zoom = Math.max(0.35, zoom);
        applyZoom();
    }

    function refreshEmptyHint() {
        const hint = document.getElementById('empty-hint');
        if (!hint) return;
        const count = canvas.getObjects().filter((o) => !o.data?.isGuide).length;
        hint.style.display = count === 0 && !canvas.backgroundImage ? 'flex' : 'none';
    }

    document.getElementById('zoom-in')?.addEventListener('click', () => {
        zoom = Math.min(2, zoom + 0.1);
        applyZoom();
    });
    document.getElementById('zoom-out')?.addEventListener('click', () => {
        zoom = Math.max(0.35, zoom - 0.1);
        applyZoom();
    });
    document.getElementById('zoom-fit')?.addEventListener('click', fitToWorkspace);

    window.addEventListener('resize', () => {
        fitToWorkspace();
    });

    fitToWorkspace();

    // --- Background import & auto-detect (legacy HTML templates) ---

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
                addPlaceholderSilent(binding, PLACEHOLDER_LABELS[binding] ?? binding.toUpperCase());
                const obj = canvas.getActiveObject();
                obj.set({ left, top });
                obj.scaleToWidth(Math.max(width, 20));
            } else {
                addTextSilent({ binding, content: '' });
                const obj = canvas.getActiveObject();
                obj.set({ left, top, width: Math.max(width, 40) });
            }
        });

        canvas.discardActiveObject();
        canvas.renderAll();
        refreshLayersPanel();
    }

    async function onBackgroundReady() {
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
        if (backgroundFrame.contentDocument?.readyState === 'complete') {
            onBackgroundReady();
        } else {
            backgroundFrame.addEventListener('load', onBackgroundReady, { once: true });
        }
    }

    // --- Initial load ---

    openPanel('text');
    refreshHistoryButtons();
    refreshPropertyPanel();

    applyPageBackground().then(() => {
        if (config.canvasJson) {
            historySuspended = true;
            deserialize(config.canvasJson);
            historySuspended = false;
        }
        refreshLayersPanel();
        refreshEmptyHint();
        // Re-fit after layout settles (sidebars measured).
        requestAnimationFrame(fitToWorkspace);
    });
}

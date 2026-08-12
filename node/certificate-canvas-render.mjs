#!/usr/bin/env node
/**
 * Chrome-free certificate renderer.
 *
 * Paints a flat list of percent-positioned elements (text / images / shapes)
 * onto a raster canvas via @napi-rs/canvas - a prebuilt Skia binary with no
 * system Chrome libraries and no headless-browser process involved. This is
 * the Node side of the "canvas" render driver; see
 * App\Services\CertificateCanvasRenderService::renderPdf() (PHP) for the
 * caller and the exact JSON spec shape produced there.
 *
 * Usage: node certificate-canvas-render.mjs <path-to-spec.json>
 */
import { createCanvas, GlobalFonts, loadImage } from '@napi-rs/canvas';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { decompress } from 'wawoff2';

/**
 * @napi-rs/canvas's image decoder is a native Skia binding - a malformed or
 * misidentified file (e.g. a non-image saved with an image extension, a
 * truncated download) can make it abort the whole process (SIGABRT) instead
 * of raising a catchable JS error, bypassing every try/catch below. Sniffing
 * the magic bytes first turns that class of bad file into a normal "skip
 * this element" instead of taking the whole render down.
 */
function looksLikeSupportedImage(buffer) {
    if (!buffer || buffer.length < 12) {
        return false;
    }

    if (buffer.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) {
        return true; // PNG
    }

    if (buffer[0] === 0xff && buffer[1] === 0xd8 && buffer[2] === 0xff) {
        return true; // JPEG
    }

    if (buffer.subarray(0, 4).toString('ascii') === 'GIF8') {
        return true; // GIF
    }

    if (buffer[0] === 0x42 && buffer[1] === 0x4d) {
        return true; // BMP
    }

    if (buffer.subarray(0, 4).toString('ascii') === 'RIFF' && buffer.subarray(8, 12).toString('ascii') === 'WEBP') {
        return true; // WEBP
    }

    return false;
}

/**
 * Reads a local image path and returns its bytes only if it passes the
 * format sniff above - null otherwise. Centralised so drawImage() and
 * paintBackground() apply the same guard before calling loadImage().
 */
function readSafeImageBytes(path, label) {
    let raw;

    try {
        raw = readFileSync(path);
    } catch (error) {
        process.stderr.write(`skipping missing/unreadable ${label} "${path}": ${error.message}\n`);

        return null;
    }

    if (!looksLikeSupportedImage(raw)) {
        process.stderr.write(`skipping ${label} with unrecognized/corrupt format: "${path}"\n`);

        return null;
    }

    return raw;
}

/**
 * Fonts ship as .woff2 (@fontsource packages) — Skia wants .ttf/.otf.
 * Decode once per font and cache to disk (path chosen by the PHP caller).
 *
 * Tamil / non-Latin glyphs: Noto Sans Tamil is registered alongside Inter /
 * Noto Sans / Playfair so Skia's font matcher can fall back instead of
 * rendering tofu boxes. Family names used in CSS `font` strings below
 * deliberately list a fallback chain (e.g. `"Inter", "Noto Sans Tamil"`).
 */
async function registerFonts(fonts) {
    for (const font of fonts ?? []) {
        try {
            if (!existsSync(font.cache)) {
                const woff2 = readFileSync(font.src);
                const ttf = await decompress(woff2);
                writeFileSync(font.cache, ttf);
            }

            GlobalFonts.registerFromPath(font.cache, font.family);
        } catch (error) {
            process.stderr.write(`font registration failed for ${font.family} weight ${font.weight}: ${error.message}\n`);
        }
    }
}

/**
 * Sum of per-character widths + spacing between them. ctx.measureText()
 * alone can't account for letter-spacing, so wrapping and alignment both
 * route through this once letterSpacing is non-zero.
 */
function measureWithSpacing(ctx, text, spacing) {
    if (!spacing || !text.length) {
        return ctx.measureText(text).width;
    }

    let width = 0;

    for (const ch of text) {
        width += ctx.measureText(ch).width + spacing;
    }

    return width - spacing;
}

function wrapLines(ctx, text, maxWidth, spacing = 0) {
    const words = String(text ?? '').split(/\s+/).filter(Boolean);
    const lines = [];
    let current = '';

    for (const word of words) {
        const candidate = current ? `${current} ${word}` : word;

        if (!current || measureWithSpacing(ctx, candidate, spacing) <= maxWidth) {
            current = candidate;
        } else {
            lines.push(current);
            current = word;
        }
    }

    if (current) {
        lines.push(current);
    }

    return lines.length ? lines : [''];
}

/**
 * Draws one line character-by-character when letter-spacing is set (Canvas
 * 2D has no native letter-spacing), stroke-then-fill so an outline (when
 * enabled) sits cleanly behind the fill instead of on top of it.
 */
function paintLine(ctx, text, x, y, spacing, mode) {
    const drawStroke = mode === 'stroke' || mode === 'both';
    const drawFill = mode === 'fill' || mode === 'both';

    if (!spacing) {
        if (drawStroke) ctx.strokeText(text, x, y);
        if (drawFill) ctx.fillText(text, x, y);

        return;
    }

    let cursor = x;

    for (const ch of text) {
        if (drawStroke) ctx.strokeText(ch, cursor, y);
        if (drawFill) ctx.fillText(ch, cursor, y);
        cursor += ctx.measureText(ch).width + spacing;
    }
}

/**
 * Auto-fit shrinks fontSize until the wrapped text's block height clears
 * the element's own box — so a title that's too long for its placeholder
 * shrinks instead of spilling over neighbouring elements.
 */
function fittedFontSize(ctx, el, box, weight, letterSpacing) {
    if (!el.autoFit) {
        return el.fontSize;
    }

    const minSize = 6;
    let size = el.fontSize;

    while (size > minSize) {
        ctx.font = `${weight} ${size}px ${fontStack(el.fontFamily)}`;
        const lines = wrapLines(ctx, el.text, box.w, letterSpacing);
        const lineHeight = size * (el.lineHeight ?? 1.2);

        if (lines.length * lineHeight <= box.h + 0.01) {
            return size;
        }

        size -= 1;
    }

    return minSize;
}

function fontStack(family) {
    // Always trail with Noto Sans Tamil so mixed Tamil+Latin text paints.
    const primary = family || 'Inter';

    if (primary === 'Noto Sans Tamil') {
        return '"Noto Sans Tamil", "Inter"';
    }

    return `"${primary}", "Noto Sans Tamil", "Noto Sans", "Inter"`;
}

function drawText(ctx, el, box) {
    const weight = parseInt(el.fontWeight, 10) >= 600 ? 'bold' : 'normal';
    const letterSpacing = el.letterSpacing || 0;
    const fontSize = fittedFontSize(ctx, el, box, weight, letterSpacing);

    ctx.font = `${weight} ${fontSize}px ${fontStack(el.fontFamily)}`;
    ctx.textBaseline = 'top';
    ctx.globalAlpha = typeof el.opacity === 'number' ? el.opacity : 1;

    if (el.gradient) {
        const gradient = ctx.createLinearGradient(0, 0, box.w, 0);
        gradient.addColorStop(0, el.gradient.from);
        gradient.addColorStop(1, el.gradient.to);
        ctx.fillStyle = gradient;
    } else {
        ctx.fillStyle = el.color || '#151c27';
    }

    if (el.outline) {
        ctx.strokeStyle = el.outline.color;
        ctx.lineWidth = el.outline.width;
        ctx.lineJoin = 'round';
    }

    if (el.shadow) {
        ctx.shadowColor = el.shadow.color;
        ctx.shadowBlur = el.shadow.blur;
        ctx.shadowOffsetX = el.shadow.offsetX;
        ctx.shadowOffsetY = el.shadow.offsetY;
    }

    const lineHeight = fontSize * (el.lineHeight ?? 1.2);
    const lines = wrapLines(ctx, el.text, box.w, letterSpacing);
    const mode = el.outline ? 'both' : 'fill';

    ctx.save();
    if (el.fontStyle === 'italic') {
        // Synthetic (sheared) italic — no dedicated italic font files are
        // registered per family, so skew the text block instead of relying
        // on font-matching to fake a slant.
        ctx.transform(1, 0, -0.25, 1, 0, 0);
    }

    lines.forEach((line, index) => {
        const lineWidth = measureWithSpacing(ctx, line, letterSpacing);
        let x = 0;

        if (el.textAlign === 'center') {
            x = (box.w - lineWidth) / 2;
        } else if (el.textAlign === 'right') {
            x = box.w - lineWidth;
        }

        const y = index * lineHeight;

        if (el.highlight && lineWidth > 0) {
            ctx.save();
            ctx.shadowColor = 'transparent';
            ctx.fillStyle = el.highlight.color;
            ctx.fillRect(x - fontSize * 0.08, y, lineWidth + fontSize * 0.16, lineHeight);
            ctx.restore();
        }

        paintLine(ctx, line, x, y, letterSpacing, mode);

        if (el.underline && lineWidth > 0) {
            const underlineY = y + fontSize * 1.05;
            ctx.fillRect(x, underlineY, lineWidth, Math.max(1, fontSize * 0.06));
        }

        if (el.linethrough && lineWidth > 0) {
            const strikeY = y + fontSize * 0.5;
            ctx.fillRect(x, strikeY, lineWidth, Math.max(1, fontSize * 0.06));
        }
    });

    ctx.restore();
    ctx.globalAlpha = 1;
    ctx.shadowColor = 'transparent';
    ctx.shadowBlur = 0;
}

async function drawImage(ctx, el, box) {
    if (!el.src) {
        return;
    }

    const raw = readSafeImageBytes(el.src, 'image');

    if (!raw) {
        return;
    }

    let image;

    try {
        image = await loadImage(raw);
    } catch (error) {
        process.stderr.write(`skipping unloadable image "${el.src}": ${error.message}\n`);

        return;
    }

    const scale = Math.min(box.w / image.width, box.h / image.height);
    const drawWidth = image.width * scale;
    const drawHeight = image.height * scale;

    ctx.drawImage(image, (box.w - drawWidth) / 2, (box.h - drawHeight) / 2, drawWidth, drawHeight);
}

/**
 * Regular N-gon inscribed in the box, point-up (first vertex at the top).
 */
function regularPolygonPoints(sides, w, h) {
    const cx = w / 2;
    const cy = h / 2;
    const rx = w / 2;
    const ry = h / 2;
    const start = -Math.PI / 2;
    const points = [];

    for (let i = 0; i < sides; i++) {
        const angle = start + (i * 2 * Math.PI) / sides;
        points.push([cx + rx * Math.cos(angle), cy + ry * Math.sin(angle)]);
    }

    return points;
}

/**
 * Regular N-gons (odd side counts especially) don't naturally touch all 4
 * edges of their own bounding box — e.g. a point-up pentagon's lowest
 * vertices sit at ~90% of the box height. Left uncorrected, re-deriving
 * vertices from a previously-saved (already slightly-short) width/height on
 * every reload would shrink the shape a little more each time. Rescaling
 * the raw vertices to exactly span [0,w]x[0,h] makes the fit exact and
 * stable across save/reload cycles.
 */
function normalizeToBox(points, w, h) {
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;

    for (const [x, y] of points) {
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
    }

    const boundsWidth = maxX - minX || 1;
    const boundsHeight = maxY - minY || 1;

    return points.map(([x, y]) => [((x - minX) / boundsWidth) * w, ((y - minY) / boundsHeight) * h]);
}

/**
 * Star / scalloped-seal polygon: alternates `spikes` outer and inner
 * vertices. A small innerRatio (close to 1) with many spikes produces a
 * certificate-seal scallop instead of a sharp star.
 */
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
        points.push([cx + rx * Math.cos(angle), cy + ry * Math.sin(angle)]);
    }

    return points;
}

/**
 * Vertex lists for every polygon-based shape kind, in a shared (w, h) box.
 * Mirrors the equivalent Fabric.js Polygon point generation in
 * certificate-builder.js — keep both in sync so the builder preview always
 * matches the final PDF.
 */
function shapeVertices(kind, w, h) {
    switch (kind) {
        case 'triangle':
            return [[w / 2, 0], [w, h], [0, h]];
        case 'diamond':
            return [[w / 2, 0], [w, h / 2], [w / 2, h], [0, h / 2]];
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
                [0, 0.3 * h], [0.6 * w, 0.3 * h], [0.6 * w, 0],
                [w, h / 2], [0.6 * w, h], [0.6 * w, 0.7 * h], [0, 0.7 * h],
            ];
        case 'banner':
            return [
                [0.08 * w, 0], [0.92 * w, 0], [w, h / 2],
                [0.92 * w, h], [0.08 * w, h], [0, h / 2],
            ];
        default:
            return null;
    }
}

/**
 * The heart's control points naturally span x:[-0.05, 1.05] / y:[0, 0.85]
 * in unit space (the two top bulges overshoot the left/right edges). Remap
 * that control-point envelope onto exactly [0,w]x[0,h] — same rationale as
 * normalizeToBox() above, and kept in sync with heartPathData() in
 * certificate-builder.js so the builder preview matches the PDF exactly.
 */
const HEART_BOUNDS = { minX: -0.05, maxX: 1.05, minY: 0, maxY: 0.85 };

function heartX(u, w) {
    return ((u - HEART_BOUNDS.minX) / (HEART_BOUNDS.maxX - HEART_BOUNDS.minX)) * w;
}

function heartY(u, h) {
    return ((u - HEART_BOUNDS.minY) / (HEART_BOUNDS.maxY - HEART_BOUNDS.minY)) * h;
}

function traceHeart(ctx, w, h) {
    const X = (u) => heartX(u, w);
    const Y = (u) => heartY(u, h);

    ctx.moveTo(X(0.5), Y(0.85));
    ctx.bezierCurveTo(X(0.15), Y(0.6), X(-0.05), Y(0.35), X(0.15), Y(0.15));
    ctx.bezierCurveTo(X(0.3), Y(0), X(0.5), Y(0.1), X(0.5), Y(0.3));
    ctx.bezierCurveTo(X(0.5), Y(0.1), X(0.7), Y(0), X(0.85), Y(0.15));
    ctx.bezierCurveTo(X(1.05), Y(0.35), X(0.85), Y(0.6), X(0.5), Y(0.85));
    ctx.closePath();
}

/**
 * Shape painting mirrors Fabric.js Rect / Ellipse / Polygon / Path
 * primitives used by the Visual Builder — same Canvas 2D API on both sides.
 */
function drawShape(ctx, el, box) {
    const fill = el.fill && el.fill !== 'transparent' ? el.fill : null;
    const stroke = el.stroke || null;
    const strokeWidth = el.strokeWidth ?? 2;
    const radius = Math.min(el.borderRadius ?? 0, Math.min(box.w, box.h) / 2);
    const kind = el.shapeKind || 'rect';

    ctx.save();
    ctx.globalAlpha = typeof el.opacity === 'number' ? el.opacity : 1;

    if (el.flipX || el.flipY) {
        ctx.translate(box.w / 2, box.h / 2);
        ctx.scale(el.flipX ? -1 : 1, el.flipY ? -1 : 1);
        ctx.translate(-box.w / 2, -box.h / 2);
    }

    ctx.beginPath();

    const vertices = shapeVertices(kind, box.w, box.h);

    if (kind === 'circle') {
        ctx.ellipse(box.w / 2, box.h / 2, box.w / 2, box.h / 2, 0, 0, Math.PI * 2);
    } else if (kind === 'heart') {
        traceHeart(ctx, box.w, box.h);
    } else if (vertices) {
        ctx.moveTo(vertices[0][0], vertices[0][1]);
        vertices.slice(1).forEach(([x, y]) => ctx.lineTo(x, y));
        ctx.closePath();
    } else if (radius > 0) {
        const r = radius;
        ctx.moveTo(r, 0);
        ctx.arcTo(box.w, 0, box.w, box.h, r);
        ctx.arcTo(box.w, box.h, 0, box.h, r);
        ctx.arcTo(0, box.h, 0, 0, r);
        ctx.arcTo(0, 0, box.w, 0, r);
        ctx.closePath();
    } else {
        ctx.rect(0, 0, box.w, box.h);
    }

    if (fill) {
        ctx.fillStyle = fill;
        ctx.fill();
    }

    if (stroke && strokeWidth > 0) {
        ctx.strokeStyle = stroke;
        ctx.lineWidth = strokeWidth;
        ctx.stroke();
    }

    ctx.restore();
}

async function paintBackground(ctx, spec) {
    ctx.fillStyle = spec.backgroundColor || '#ffffff';
    ctx.fillRect(0, 0, spec.width, spec.height);

    if (!spec.backgroundImage) {
        return;
    }

    const raw = readSafeImageBytes(spec.backgroundImage, 'background image');

    if (!raw) {
        return;
    }

    try {
        const image = await loadImage(raw);
        // Cover the full page (object-fit: cover equivalent) so decorative
        // certificate backgrounds always fill the page edges.
        const scale = Math.max(spec.width / image.width, spec.height / image.height);
        const drawWidth = image.width * scale;
        const drawHeight = image.height * scale;
        ctx.drawImage(
            image,
            (spec.width - drawWidth) / 2,
            (spec.height - drawHeight) / 2,
            drawWidth,
            drawHeight,
        );
    } catch (error) {
        process.stderr.write(`skipping missing background image "${spec.backgroundImage}": ${error.message}\n`);
    }
}

async function main() {
    const specPath = process.argv[2];

    if (!specPath) {
        throw new Error('Usage: node certificate-canvas-render.mjs <spec.json>');
    }

    const spec = JSON.parse(readFileSync(specPath, 'utf8'));

    await registerFonts(spec.fonts);

    const canvas = createCanvas(spec.width, spec.height);
    const ctx = canvas.getContext('2d');

    await paintBackground(ctx, spec);

    for (const el of spec.elements ?? []) {
        const box = {
            x: (el.xPercent / 100) * spec.width,
            y: (el.yPercent / 100) * spec.height,
            w: (el.widthPercent / 100) * spec.width,
            h: (el.heightPercent / 100) * spec.height,
        };

        ctx.save();
        ctx.translate(box.x + box.w / 2, box.y + box.h / 2);
        ctx.rotate(((el.rotation || 0) * Math.PI) / 180);
        ctx.translate(-box.w / 2, -box.h / 2);

        if (el.kind === 'image') {
            await drawImage(ctx, el, box);
        } else if (el.kind === 'shape') {
            drawShape(ctx, el, box);
        } else {
            drawText(ctx, el, box);
        }

        ctx.restore();
    }

    writeFileSync(spec.outputPath, canvas.toBuffer('image/png'));
}

main().catch((error) => {
    process.stderr.write(`${error.stack || error.message}\n`);
    process.exit(1);
});

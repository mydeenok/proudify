<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\CertificateRenderService;
use App\Services\LayoutToHtmlRenderer;
use App\Services\TemplateBackgroundImportService;
use App\Services\TemplateThumbnailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CertificateBuilderController extends Controller
{
    public function edit(Template $template, TemplateBackgroundImportService $importService): View
    {
        $existingBackground = $template->canvas_json['background_html'] ?? null;

        // Every template's html_content is hand-written at creation time
        // (see TemplateController) — canvas_json only exists after the
        // first builder save. A null canvas_json always means "this
        // template needs its raw HTML imported as a background": show it
        // marked up for auto-detection. Once background_html has already
        // been captured, just display it as-is — the fields it used to
        // contain are now tracked as real elements via the normal
        // canvas_json['elements'] load path, so detection never re-runs.
        [$backgroundHtml, $needsAutoDetect] = match (true) {
            $existingBackground !== null => [$existingBackground, false],
            $template->canvas_json === null && filled($template->html_content) => [$importService->renderForDetection($template->html_content), true],
            default => [null, false],
        };

        return view('admin.templates.builder', [
            'template' => $template,
            'backgroundDetectionHtml' => $backgroundHtml,
            'needsAutoDetect' => $needsAutoDetect,
            'pageBackground' => is_array($template->canvas_json) ? ($template->canvas_json['background'] ?? null) : null,
        ]);
    }

    /**
     * Autosave the working canvas state. Never touches html_content, so a
     * template currently issuing certificates is unaffected until Publish.
     */
    public function save(Request $request, Template $template, TemplateBackgroundImportService $importService): JsonResponse
    {
        $validated = $request->validate([
            'canvas_json' => ['required', 'array'],
            // LayoutToHtmlRenderer::render() does
            // collect($elements)->map(fn (array $element) => ...) with a
            // hard `array` type hint on every entry - without this rule, a
            // malformed payload (e.g. one element serialized as a string)
            // threw an uncaught TypeError instead of a clean validation
            // error.
            'canvas_json.elements' => ['sometimes', 'array'],
            'canvas_json.elements.*' => ['array'],
            // The version this client last loaded/saved - see claimVersion().
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $canvasJson = $this->withBackgroundHtml($template, $validated['canvas_json'], $importService);

        $conflict = $this->claimVersion($template, (int) $validated['version'], [
            'canvas_json' => $canvasJson,
        ]);

        if ($conflict) {
            return $conflict;
        }

        return response()->json(['status' => 'saved', 'saved_at' => now()->toIso8601String(), 'version' => $validated['version'] + 1]);
    }

    /**
     * Renders the current canvas to html_content — the field the
     * Milestone 2 Browsershot pipeline actually reads at issuance time —
     * and marks the template live.
     */
    public function publish(
        Request $request,
        Template $template,
        LayoutToHtmlRenderer $renderer,
        TemplateBackgroundImportService $importService,
        TemplateThumbnailService $thumbnails,
    ): JsonResponse {
        $validated = $request->validate([
            'canvas_json' => ['required', 'array'],
            // LayoutToHtmlRenderer::render() does
            // collect($elements)->map(fn (array $element) => ...) with a
            // hard `array` type hint on every entry - without this rule, a
            // malformed payload (e.g. one element serialized as a string)
            // threw an uncaught TypeError instead of a clean validation
            // error.
            'canvas_json.elements' => ['sometimes', 'array'],
            'canvas_json.elements.*' => ['array'],
            // The version this client last loaded/saved - see claimVersion().
            'version' => ['required', 'integer', 'min:1'],
        ]);

        $canvasJson = $this->withBackgroundHtml($template, $validated['canvas_json'], $importService);

        // Invalidate any stale corner so the geometry / Browsershot
        // detector re-runs against the newly published layout.
        $conflict = $this->claimVersion($template, (int) $validated['version'], [
            'canvas_json' => $canvasJson,
            'html_content' => $renderer->render($canvasJson),
            'custom_field_schema' => $this->deriveCustomFieldSchema($canvasJson, $importService),
            'watermark_corner' => null,
            'is_active' => true,
        ]);

        if ($conflict) {
            return $conflict;
        }

        // Best-effort card thumbnail from the same canvas painter — publish
        // still succeeds if Node/fonts are unavailable on this host.
        $thumbnails->generateQuietly($template->fresh(), $request->user());

        return response()->json(['status' => 'published', 'redirect' => route('admin.templates.index')]);
    }

    /**
     * Atomically claims this template for the given expected version - the
     * conditional UPDATE ... WHERE version = ? is itself the concurrency
     * guard, no separate lock/transaction needed. Two tabs/editors on the
     * same template used to silently overwrite each other's canvas_json on
     * autosave (last write wins, no conflict detection); now only the
     * first save/publish to arrive for a given version actually lands -
     * anything after that (from a client working off a now-stale version)
     * gets a 409 instead of quietly clobbering what the other tab wrote.
     *
     * @param  array<string, mixed>  $attributes
     * @return JsonResponse|null null on success, a 409 JsonResponse on conflict
     */
    private function claimVersion(Template $template, int $expectedVersion, array $attributes): ?JsonResponse
    {
        $claimed = Template::whereKey($template->id)
            ->where('version', $expectedVersion)
            ->update([...$attributes, 'version' => $expectedVersion + 1]);

        if ($claimed > 0) {
            return null;
        }

        $currentVersion = Template::whereKey($template->id)->value('version');

        return response()->json([
            'status' => 'conflict',
            'message' => 'This template was changed elsewhere (another tab, or another admin). Reload the page to see the latest version before continuing.',
            'currentVersion' => $currentVersion,
        ], 409);
    }

    /**
     * Upload a decorative asset used as a page background or unbound
     * image element. Stored on the public disk so the builder can preview
     * it via Storage::url without a second authenticated download route.
     */
    public function uploadAsset(Request $request, Template $template): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'image', 'max:5120'],
        ]);

        $path = $validated['file']->store("templates/{$template->id}/assets", 'public');

        // Relative URL so the builder works regardless of APP_URL port
        // (e.g. localhost:3000 vs APP_URL=http://localhost).
        return response()->json([
            'path' => $path,
            'url' => '/storage/'.$path,
        ]);
    }

    /**
     * Builder "Preview sample" — same painter as create Live Preview /
     * issued PDF, with placeholder form values so admins can check layout
     * without leaving the editor.
     */
    public function previewSample(Request $request, Template $template, CertificateRenderService $renderService): Response
    {
        $preview = $renderService->renderPreview($template, $request->user(), [
            'title' => 'Sample Certificate Title',
            'recipient_name' => 'Alex Recipient',
            'description' => 'For outstanding achievement in the sample program.',
            'date_of_issue' => now()->toDateString(),
        ]);

        return response($preview['body'])
            ->header('Content-Type', $preview['contentType'])
            ->header('X-Preview-Mode', $preview['mode']);
    }

    /**
     * Any canvas element bound to something outside the fixed system token
     * set is, by definition, an admin-defined custom field — there's no
     * separate "mark as customizable" step, since adding a field beyond the
     * system set only ever means one thing: a per-certificate value the
     * issuing user is meant to fill in. This schema is what Phase 3's
     * certificate-create form and inline editor read to know which fields
     * to render, so it's rebuilt fresh on every publish rather than edited
     * by hand.
     *
     * @param  array{elements?: array<int, array<string, mixed>>}  $canvasJson
     * @return array<int, array{key: string, label: string, type: string, required: bool}>
     */
    private function deriveCustomFieldSchema(array $canvasJson, TemplateBackgroundImportService $importService): array
    {
        $systemTokens = $importService->systemTokenKeys();
        $schema = [];
        $seenKeys = [];

        foreach ($canvasJson['elements'] ?? [] as $element) {
            $binding = $element['binding'] ?? null;
            $type = $element['type'] ?? 'text';

            if (! is_string($binding) || $binding === '' || isset($seenKeys[$binding])) {
                continue;
            }

            if (in_array($binding, $systemTokens, true) || preg_match('/^company_logo_\d+$/', $binding) === 1) {
                continue;
            }

            if (! in_array($type, ['text', 'image'], true)) {
                continue;
            }

            $seenKeys[$binding] = true;
            $schema[] = [
                'key' => $binding,
                'label' => is_string($element['label'] ?? null) && $element['label'] !== ''
                    ? $element['label']
                    : Str::headline($binding),
                'type' => $type,
                'required' => false,
            ];
        }

        return $schema;
    }

    /**
     * A template's canvas_json is null until its first builder save/publish
     * — at that exact moment, its (hand-written) html_content is the raw
     * material for the locked background layer, with the fields the user
     * just promoted to overlay elements stripped out so they don't render
     * twice. Once background_html exists, it's carried forward unchanged
     * on every later save — the client never sends it back.
     *
     * @param  array<string, mixed>  $canvasJson
     * @return array<string, mixed>
     */
    private function withBackgroundHtml(Template $template, array $canvasJson, TemplateBackgroundImportService $importService): array
    {
        $existingBackground = $template->canvas_json['background_html'] ?? null;

        if ($existingBackground !== null) {
            $canvasJson['background_html'] = $existingBackground;

            return $canvasJson;
        }

        if ($template->canvas_json === null && filled($template->html_content)) {
            $canvasJson['background_html'] = $importService->blankKnownTokens($template->html_content);
        }

        return $canvasJson;
    }
}

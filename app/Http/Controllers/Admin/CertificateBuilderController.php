<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Services\LayoutToHtmlRenderer;
use App\Services\TemplateBackgroundImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        ]);

        $template->update(['canvas_json' => $this->withBackgroundHtml($template, $validated['canvas_json'], $importService)]);

        return response()->json(['status' => 'saved', 'saved_at' => now()->toIso8601String()]);
    }

    /**
     * Renders the current canvas to html_content — the field the
     * Milestone 2 Browsershot pipeline actually reads at issuance time —
     * and marks the template live.
     */
    public function publish(Request $request, Template $template, LayoutToHtmlRenderer $renderer, TemplateBackgroundImportService $importService): JsonResponse
    {
        $validated = $request->validate([
            'canvas_json' => ['required', 'array'],
        ]);

        $canvasJson = $this->withBackgroundHtml($template, $validated['canvas_json'], $importService);

        $template->update([
            'canvas_json' => $canvasJson,
            'html_content' => $renderer->render($canvasJson),
            'is_active' => true,
        ]);

        return response()->json(['status' => 'published', 'redirect' => route('admin.templates.index')]);
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

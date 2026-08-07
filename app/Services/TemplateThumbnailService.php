<?php

namespace App\Services;

use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Auto-builds the card thumbnail for a Visual Builder template by painting
 * the same sample preview PNG used in the builder "Preview sample" drawer.
 * Callers should treat generation as best-effort — publish must not fail if
 * Node/canvas is unavailable on a given host.
 */
class TemplateThumbnailService
{
    public function __construct(
        private CertificateCanvasRenderService $canvasRenderService,
        private QrCodeService $qrCodeService,
    ) {}

    /**
     * @return string|null Relative public-disk path, or null when skipped/failed
     */
    public function generateFromCanvas(Template $template, User $issuer): ?string
    {
        if (! $this->canvasRenderService->supportsTemplate($template)) {
            return null;
        }

        try {
            $png = $this->canvasRenderService->renderPreviewPng(
                $template,
                $issuer,
                $this->sampleFormData(),
                $this->qrCodeService,
            );
        } catch (Throwable $e) {
            Log::warning('Template thumbnail render failed.', [
                'template_id' => $template->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        $path = "templates/{$template->id}/thumbnail.png";
        Storage::disk('public')->put($path, $png);

        $previous = $template->thumbnail_path;
        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $template->update(['thumbnail_path' => $path]);

        return $path;
    }

    /**
     * Best-effort wrapper for publish / CLI — never throws.
     */
    public function generateQuietly(Template $template, User $issuer): ?string
    {
        try {
            return $this->generateFromCanvas($template, $issuer);
        } catch (Throwable $e) {
            Log::warning('Template thumbnail generation failed.', [
                'template_id' => $template->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{title: string, recipient_name: string, description: string, date_of_issue: string}
     */
    private function sampleFormData(): array
    {
        return [
            'title' => 'Sample Certificate Title',
            'recipient_name' => 'Alex Recipient',
            'description' => 'For outstanding achievement in the sample program.',
            'date_of_issue' => now()->toDateString(),
        ];
    }
}

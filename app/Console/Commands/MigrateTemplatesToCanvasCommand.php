<?php

namespace App\Console\Commands;

use App\Models\Template;
use Illuminate\Console\Command;

/**
 * One-shot admin helper for Phase 1 of the Chrome-free builder migration.
 * Hand-written HTML templates fail under CERTIFICATE_RENDER_DRIVER=canvas
 * until they have a pure canvas_json (no background_html). This scaffolds
 * a starting layout of shape border + system fields so admins only have
 * to re-skin / upload a background in the Visual Builder, not start from
 * a blank page.
 */
class MigrateTemplatesToCanvasCommand extends Command
{
    protected $signature = 'templates:migrate-to-canvas
                            {--dry-run : List what would change without writing}
                            {--force : Overwrite templates that already have canvas_json}';

    protected $description = 'Scaffold canvas_json for legacy HTML templates so they render via the Chrome-free driver';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $templates = Template::query()->orderBy('id')->get();
        $changed = 0;

        foreach ($templates as $template) {
            $needsScaffold = $force
                || ! is_array($template->canvas_json)
                || empty($template->canvas_json['elements'])
                || ($template->canvas_json['background_html'] ?? null) !== null;

            if (! $needsScaffold) {
                $this->line("skip  #{$template->id} {$template->name} (already canvas-compatible)");

                continue;
            }

            $scaffold = $this->scaffold($template);

            $this->info(($dryRun ? 'would update' : 'update')."  #{$template->id} {$template->name}");

            if (! $dryRun) {
                $template->forceFill([
                    'canvas_json' => $scaffold,
                    'watermark_corner' => null,
                    // Keep inactive so admins review in the builder before
                    // the scaffold starts issuing certificates live.
                    'is_active' => false,
                ])->save();
            }

            $changed++;
        }

        $this->newLine();
        $this->info(($dryRun ? "Would scaffold {$changed}" : "Scaffolded {$changed}").' template(s).');
        $this->comment('Open each draft in Admin → Templates → Builder, restyle/upload a background, then Publish.');

        return self::SUCCESS;
    }

    /**
     * @return array{background: array{type: string, value: string}, elements: array<int, array<string, mixed>>}
     */
    private function scaffold(Template $template): array
    {
        $orientation = $template->orientation === 'portrait' ? 'portrait' : 'landscape';

        // Percent boxes roughly match a classic certificate layout so the
        // admin has something to drag rather than a blank page.
        $elements = [
            [
                'id' => 'el_border',
                'type' => 'shape',
                'shapeKind' => 'rect',
                'binding' => null,
                'label' => 'Border',
                'xPercent' => 2, 'yPercent' => 2, 'widthPercent' => 96, 'heightPercent' => 96,
                'rotation' => 0, 'z' => 0,
                'style' => ['fill' => 'transparent', 'stroke' => '#b40012', 'strokeWidth' => 6, 'borderRadius' => 0],
            ],
            [
                'id' => 'el_title',
                'type' => 'text',
                'binding' => 'title',
                'label' => 'Certificate Title',
                'xPercent' => 10, 'yPercent' => $orientation === 'portrait' ? 12 : 15,
                'widthPercent' => 80, 'heightPercent' => 8,
                'rotation' => 0, 'z' => 1,
                'content' => null,
                'style' => ['fontFamily' => 'Playfair Display', 'fontSize' => 36, 'fontWeight' => '700', 'color' => '#151c27', 'textAlign' => 'center'],
            ],
            [
                'id' => 'el_recipient',
                'type' => 'text',
                'binding' => 'recipient_name',
                'label' => 'Recipient Name',
                'xPercent' => 10, 'yPercent' => $orientation === 'portrait' ? 28 : 32,
                'widthPercent' => 80, 'heightPercent' => 8,
                'rotation' => 0, 'z' => 2,
                'content' => null,
                'style' => ['fontFamily' => 'Inter', 'fontSize' => 28, 'fontWeight' => '700', 'color' => '#b40012', 'textAlign' => 'center'],
            ],
            [
                'id' => 'el_description',
                'type' => 'text',
                'binding' => 'description',
                'label' => 'Description',
                'xPercent' => 15, 'yPercent' => $orientation === 'portrait' ? 40 : 45,
                'widthPercent' => 70, 'heightPercent' => 12,
                'rotation' => 0, 'z' => 3,
                'content' => null,
                'style' => ['fontFamily' => 'Inter', 'fontSize' => 16, 'fontWeight' => '400', 'color' => '#151c27', 'textAlign' => 'center'],
            ],
            [
                'id' => 'el_org',
                'type' => 'text',
                'binding' => 'organization_name',
                'label' => 'Organization Name',
                'xPercent' => 10, 'yPercent' => 70,
                'widthPercent' => 35, 'heightPercent' => 6,
                'rotation' => 0, 'z' => 4,
                'content' => null,
                'style' => ['fontFamily' => 'Inter', 'fontSize' => 14, 'fontWeight' => '400', 'color' => '#151c27', 'textAlign' => 'left'],
            ],
            [
                'id' => 'el_date',
                'type' => 'text',
                'binding' => 'date_of_issue',
                'label' => 'Issue Date',
                'xPercent' => 55, 'yPercent' => 70,
                'widthPercent' => 35, 'heightPercent' => 6,
                'rotation' => 0, 'z' => 5,
                'content' => null,
                'style' => ['fontFamily' => 'Inter', 'fontSize' => 14, 'fontWeight' => '400', 'color' => '#151c27', 'textAlign' => 'right'],
            ],
            [
                'id' => 'el_qr',
                'type' => 'qrcode',
                'binding' => 'qrcode',
                'label' => 'QR Code',
                'xPercent' => 5, 'yPercent' => 80,
                'widthPercent' => 12, 'heightPercent' => 14,
                'rotation' => 0, 'z' => 6,
            ],
            [
                'id' => 'el_signature',
                'type' => 'signature',
                'binding' => 'signature',
                'label' => 'Signature',
                'xPercent' => 70, 'yPercent' => 80,
                'widthPercent' => 20, 'heightPercent' => 12,
                'rotation' => 0, 'z' => 7,
            ],
            [
                'id' => 'el_logo',
                'type' => 'company_logo',
                'binding' => 'company_logo',
                'label' => 'Company Logo',
                'xPercent' => 40, 'yPercent' => 82,
                'widthPercent' => 15, 'heightPercent' => 10,
                'rotation' => 0, 'z' => 8,
            ],
        ];

        return [
            'background' => ['type' => 'color', 'value' => '#ffffff'],
            'elements' => $elements,
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Template;
use App\Models\User;
use App\Services\CertificateCanvasRenderService;
use App\Services\TemplateThumbnailService;
use Illuminate\Console\Command;

class GenerateTemplateThumbnailsCommand extends Command
{
    protected $signature = 'templates:generate-thumbnails
                            {--template= : Only this template id}
                            {--force : Overwrite existing thumbnail_path}
                            {--dry-run : List templates that would be processed}';

    protected $description = 'Generate card thumbnails from Visual Builder canvas (no upload).';

    public function handle(TemplateThumbnailService $thumbnails, CertificateCanvasRenderService $canvas): int
    {
        $issuer = User::query()->admins()->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $issuer) {
            $this->error('No users found to act as sample issuer for logos/signatures.');

            return self::FAILURE;
        }

        $query = Template::query()->orderBy('id');
        if ($id = $this->option('template')) {
            $query->whereKey($id);
        }

        $processed = 0;
        $skipped = 0;

        foreach ($query->cursor() as $template) {
            if (! $canvas->supportsTemplate($template)) {
                $this->line("skip #{$template->id} {$template->name} (not canvas-compatible)");
                $skipped++;

                continue;
            }

            if (! $this->option('force') && filled($template->thumbnail_path)) {
                $this->line("skip #{$template->id} {$template->name} (already has thumbnail)");
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->info("would generate #{$template->id} {$template->name}");
                $processed++;

                continue;
            }

            $path = $thumbnails->generateQuietly($template, $issuer);
            if ($path === null) {
                $this->warn("failed #{$template->id} {$template->name}");
                $skipped++;

                continue;
            }

            $this->info("generated #{$template->id} → {$path}");
            $processed++;
        }

        $this->newLine();
        $this->info("Done. processed={$processed} skipped={$skipped}");

        return self::SUCCESS;
    }
}

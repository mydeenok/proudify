<?php

namespace App\Console\Commands;

use App\Jobs\Certificates\ConvertCertificatePdfToImageJob;
use App\Jobs\Certificates\GenerateCertificatePdfJob;
use App\Models\Certificate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

class RegenerateCertificateAssetsCommand extends Command
{
    protected $signature = 'certificates:regenerate-assets {id? : Certificate ID to regenerate (all when omitted)}';

    protected $description = 'Rebuild certificate PDF and preview image assets using the latest template data';

    public function handle(): int
    {
        $query = Certificate::query()->with('template');

        if ($id = $this->argument('id')) {
            $query->whereKey($id);
        }

        $certificates = $query->get();

        if ($certificates->isEmpty()) {
            $this->warn('No certificates found to regenerate.');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($certificates as $certificate) {
            if (! $certificate->qr_code_path) {
                $this->warn("Skipping certificate #{$certificate->id}: QR code not generated yet.");

                continue;
            }

            $this->info("Regenerating certificate #{$certificate->id} ({$certificate->recipient_name})...");

            // One certificate's render crashing (e.g. the Node/Skia
            // renderer aborting on a broken template asset) used to
            // propagate straight out of this loop, silently skipping every
            // remaining certificate in the run with no per-row error
            // reporting - worst in regenerate-all mode with no {id} given.
            try {
                Bus::dispatchSync(new GenerateCertificatePdfJob($certificate));
                Bus::dispatchSync(new ConvertCertificatePdfToImageJob($certificate));

                $certificate->refresh();
                $this->line("  pdf: {$certificate->pdf_path}");
                $this->line("  image: {$certificate->image_path}");
            } catch (Throwable $exception) {
                $failures++;
                $this->error("  failed: {$exception->getMessage()}");
            }
        }

        if ($failures > 0) {
            $this->warn("Done with {$failures} failure(s) - see above.");

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}

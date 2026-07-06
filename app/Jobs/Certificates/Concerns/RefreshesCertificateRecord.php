<?php

namespace App\Jobs\Certificates\Concerns;

use App\Models\Certificate;

trait RefreshesCertificateRecord
{
    protected function freshCertificate(): Certificate
    {
        $this->certificate->refresh();
        $this->certificate->loadMissing('template', 'user');

        return $this->certificate;
    }
}

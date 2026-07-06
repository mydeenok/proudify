<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateVerification extends Model
{
    protected $fillable = [
        'certificate_id',
        'ip_address',
        'user_agent',
        'result',
    ];

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}

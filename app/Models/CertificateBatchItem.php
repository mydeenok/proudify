<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateBatchItem extends Model
{
    protected $fillable = [
        'certificate_batch_id',
        'row_number',
        'row_data',
        'status',
        'error_message',
        'certificate_id',
    ];

    protected function casts(): array
    {
        return [
            'row_data' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CertificateBatch::class, 'certificate_batch_id');
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}

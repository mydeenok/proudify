<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CertificateBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'template_id',
        'issued_by',
        'original_filename',
        'total_rows',
        'processed_rows',
        'succeeded_rows',
        'failed_rows',
        'status',
        'column_mapping',
        'temp_upload_path',
        'error_report_path',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CertificateBatchItem::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Only present for tenant-paid batches - an admin-issued batch (see
     * BulkUploadWizardService::createAdminBatch) never gets a
     * CertificateOrder, since admin issuance stays free.
     *
     * latestOfMany() (not a plain hasOne) because a batch can end up with
     * more than one CertificateOrder row - e.g. the first checkout link
     * expires and the user retries, creating a second order for the same
     * batch. An unordered hasOne could resolve to the stale/expired order
     * instead of the one that was actually paid, wrongly refusing to
     * dispatch a batch the customer did pay for.
     */
    public function certificateOrder(): HasOne
    {
        return $this->hasOne(CertificateOrder::class, 'certificate_batch_id')->latestOfMany();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'completed_with_errors', 'failed'], true);
    }

    /**
     * total_rows includes rows skipped/failed at ingest time, which never
     * pass through the chunk job and so never increment processed_rows —
     * dividing straight through would asymptote below 100% even once the
     * batch has genuinely finished. "finished" is the authoritative signal.
     */
    public function progressPercent(): int
    {
        if ($this->isFinished()) {
            return 100;
        }

        if ($this->total_rows === 0) {
            return 0;
        }

        return (int) round(($this->processed_rows / $this->total_rows) * 100);
    }
}

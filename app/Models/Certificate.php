<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'verification_code',
        'verification_signature',
        'user_id',
        'template_id',
        'certificate_batch_id',
        'user_subscription_id',
        'title',
        'recipient_name',
        'recipient_email',
        'description',
        'date_of_issue',
        'date_of_expiry',
        'custom_fields',
        'company_logos',
        'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'date_of_issue' => 'date',
            'date_of_expiry' => 'date',
            'custom_fields' => 'array',
            'company_logos' => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Certificate $certificate) {
            if (empty($certificate->uuid)) {
                $certificate->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Owner-facing routes (show/download) resolve by UUID rather than the
     * sequential primary key, so URLs don't leak issuance order/volume.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function certificateBatch(): BelongsTo
    {
        return $this->belongsTo(CertificateBatch::class);
    }

    public function userSubscription(): BelongsTo
    {
        return $this->belongsTo(UserSubscription::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(CertificateVerification::class);
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function isExpired(): bool
    {
        return $this->date_of_expiry !== null && $this->date_of_expiry->isPast();
    }

    /**
     * The display status derived at read time rather than stored, so there
     * is no scheduled job needed just to flip rows to "expired".
     */
    public function displayStatus(): string
    {
        return match (true) {
            $this->isRevoked() => 'revoked',
            $this->isExpired() => 'expired',
            default => 'active',
        };
    }

    public function getVerifyUrlAttribute(): string
    {
        return route('certificates.verify', [
            'uuid' => $this->uuid,
            'code' => $this->verification_code,
        ]);
    }
}

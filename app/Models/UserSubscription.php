<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSubscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'certificates_limit',
        'users_limit',
        'certificates_used',
        'users_used',
        'current_period_started_at',
        'billing_period',
        'amount_paid',
        'currency',
        'payment_status',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'payment_verified_at',
        'start_date',
        'end_date',
        'is_active',
        'auto_renew',
        'payment_details',
    ];

    protected function casts(): array
    {
        return [
            'current_period_started_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
            'auto_renew' => 'boolean',
            'amount_paid' => 'decimal:2',
            'payment_details' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isExpired(): bool
    {
        return $this->end_date->isPast();
    }

    public function isUsable(): bool
    {
        return $this->is_active && $this->payment_status === 'completed' && ! $this->isExpired();
    }

    public function remainingCertificates(): int
    {
        return max(0, $this->certificates_limit - $this->certificates_used);
    }

    public function remainingUsers(): int
    {
        return max(0, $this->users_limit - $this->users_used);
    }

    public function hasCertificateQuota(): bool
    {
        return $this->remainingCertificates() > 0;
    }
}

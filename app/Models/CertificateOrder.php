<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CertificateOrder extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'type',
        'template_id',
        'certificate_batch_id',
        'certificate_id',
        'payload',
        'quantity',
        'unit_price',
        'subtotal',
        'discount_percent',
        'discount_amount',
        'total_amount',
        'currency',
        'status',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'razorpay_refund_id',
        'paid_at',
        'expires_at',
        'refunded_at',
        'refunded_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CertificateOrder $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });
    }

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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CertificateBatch::class, 'certificate_batch_id');
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }

    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isPayable(): bool
    {
        return $this->status === 'pending' && (! $this->expires_at || $this->expires_at->isFuture());
    }
}

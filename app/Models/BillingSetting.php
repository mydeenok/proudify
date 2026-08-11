<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class BillingSetting extends Model
{
    private const CACHE_KEY = 'billing_settings.current';

    protected $fillable = [
        'price_per_certificate_inr',
        'bulk_discount_threshold',
        'bulk_discount_percent',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price_per_certificate_inr' => 'decimal:2',
            'bulk_discount_threshold' => 'integer',
            'bulk_discount_percent' => 'decimal:2',
        ];
    }

    /**
     * Read on every single-certificate submission and every bulk-review
     * page load, so this is cached - the single row is only ever written
     * from the admin settings screen, which clears the cache on save.
     */
    public static function current(): self
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function () {
            return static::query()->firstOrCreate(['id' => 1], [
                'price_per_certificate_inr' => 20.00,
                'bulk_discount_threshold' => 5,
                'bulk_discount_percent' => 10.00,
            ]);
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

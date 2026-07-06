<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'thumbnail_path',
        'certificates_per_month',
        'certificates_per_year',
        'users_per_month',
        'users_per_year',
        'cost_month_inr',
        'cost_year_inr',
        'cost_month_usd',
        'cost_year_usd',
        'is_default_free_plan',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'cost_month_inr' => 'decimal:2',
            'cost_year_inr' => 'decimal:2',
            'cost_month_usd' => 'decimal:2',
            'cost_year_usd' => 'decimal:2',
            'is_default_free_plan' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            if (empty($subscription->slug)) {
                $subscription->slug = Str::slug($subscription->name);
            }
        });
    }

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isFree(): bool
    {
        return (float) $this->cost_month_inr === 0.0 && (float) $this->cost_month_usd === 0.0;
    }

    /**
     * @return array{certificates: int, users: int}
     */
    public function limitsFor(string $billingPeriod): array
    {
        return [
            'certificates' => $billingPeriod === 'yearly' ? $this->certificates_per_year : $this->certificates_per_month,
            'users' => $billingPeriod === 'yearly' ? $this->users_per_year : $this->users_per_month,
        ];
    }

    public function priceFor(string $billingPeriod, string $currency): float
    {
        $column = 'cost_'.($billingPeriod === 'yearly' ? 'year' : 'month').'_'.strtolower($currency);

        return (float) ($this->{$column} ?? 0);
    }
}

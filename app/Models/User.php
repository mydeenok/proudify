<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * 'role' and 'status' are deliberately excluded here even though
     * several code paths need to set them (registration, SSO signup, admin
     * approve/reject/suspend) - those all use forceFill()/explicit array
     * merges instead, so a future `update($request->all())` or
     * `fill($request->all())` mistake can never let a tenant self-escalate
     * to admin or flip their own status to active.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'organization_name',
        'email',
        'phone',
        'password',
        'org_logos',
        'signature_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'org_logos' => 'array',
            'otp_expires_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function getNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Every staff account that should hear about platform-wide events
     * (new registrations, bulk-upload requests, purchases) — a small
     * install has one admin, but this stays correct as more are added.
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', 'admin');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    public function userSubscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'created_by');
    }
}

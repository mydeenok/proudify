<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'website_name',
        'website_url',
        'token',
        'abilities',
        'expires_at',
    ];

    public function requestLogs(): HasMany
    {
        return $this->hasMany(ApiRequestLog::class, 'personal_access_token_id');
    }
}

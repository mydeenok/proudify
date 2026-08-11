<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiRequestLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'personal_access_token_id',
        'method',
        'path',
        'status_code',
        'ip_address',
        'origin',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Template extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'html_content',
        'canvas_json',
        'version',
        'custom_field_schema',
        'watermark_corner',
        'thumbnail_path',
        'page_format',
        'orientation',
        'is_active',
        'is_exclusive',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_exclusive' => 'boolean',
            'canvas_json' => 'array',
            'custom_field_schema' => 'array',
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required: bool}>
     */
    public function editableCustomFields(): array
    {
        return array_values(array_filter(
            (array) $this->custom_field_schema,
            fn ($field) => is_array($field) && isset($field['key'], $field['type'])
        ));
    }

    protected static function booted(): void
    {
        static::creating(function (Template $template) {
            if (empty($template->slug)) {
                $template->slug = static::uniqueSlugFor($template->name);
            }
        });
    }

    private static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

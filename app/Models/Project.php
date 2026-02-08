<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, HasUuids;

    public function uniqueIds()
    {
        return ['uuid'];
    }



    protected $fillable = [
        'user_id',
        'uuid',
        'name',
        'business_name',
        'niche',
        'domain_url',
        'target_language',
        'brand_voice',
        'target_city',
        'cms_type',
        'integration_type',
        'wp_api_url',
        'wp_username',
        'wp_application_password',
        'posting_frequency',
        'next_run_at',
        'business_context',
        'target_location',
        'target_audience',
        'key_services',
        'cta_instruction',
    ];

    protected $casts = [
        'next_run_at' => 'datetime',
    ];



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}

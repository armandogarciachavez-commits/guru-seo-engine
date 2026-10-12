<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoOpportunity extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'project_id',
        'query',
        'page',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'potential_clicks',
        'priority_score',
        'status',
        'ai_suggestions',
        'window_from',
        'window_to',
        'detected_at',
    ];

    protected $casts = [
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
        'potential_clicks' => 'integer',
        'priority_score' => 'float',
        'ai_suggestions' => 'array',
        'window_from' => 'date',
        'window_to' => 'date',
        'detected_at' => 'datetime',
    ];

    /** Oportunidades aún accionables (ni aplicadas ni descartadas). */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_NEW, self::STATUS_IN_PROGRESS]);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

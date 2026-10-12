<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GscMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'window_from',
        'window_to',
        'query',
        'page',
        'clicks',
        'impressions',
        'ctr',
        'position',
        'captured_at',
    ];

    protected $casts = [
        'window_from' => 'date',
        'window_to' => 'date',
        'clicks' => 'integer',
        'impressions' => 'integer',
        'ctr' => 'float',
        'position' => 'float',
        'captured_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}

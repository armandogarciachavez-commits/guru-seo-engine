<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'slug',
        'keyword',
        'scheduled_date',
        'content',
        'html_content',
        'competitor_data',
        'image_url',
        'status',
        'is_published',
        'published_at',
        'published_url',
        'quality_issues',
        'thumbnail_url',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_date' => 'date',
        'competitor_data' => 'array',
        'quality_issues' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
        });

        // Mantiene sincronizadas las columnas 'content' y 'html_content':
        // distintos flujos escriben/leen una u otra, ambas contienen el HTML del artículo.
        static::saving(function ($article) {
            if ($article->isDirty('content') && ! $article->isDirty('html_content')) {
                $article->html_content = $article->content;
            } elseif ($article->isDirty('html_content') && ! $article->isDirty('content')) {
                $article->content = $article->html_content;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
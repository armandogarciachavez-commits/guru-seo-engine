<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str; // <--- 1. IMPORTANTE: La herramienta para crear slugs

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'title',
        'slug', // <--- 2. Asegúrate que esté autorizado
        'content',
        'image_url',
        'status',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * 3. MAGIA: Al crear, si no hay slug, lo creamos desde el título.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (empty($article->slug)) {
                // Convierte "Qué es un Coach?" en "que-es-un-coach"
                $article->slug = Str::slug($article->title);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
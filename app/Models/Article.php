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
        'keyword',        // <--- FALTABA ESTE (Vital para la IA)
        'scheduled_date', // <--- FALTABA ESTE (Vital para el calendario)
        'content',        // <--- Este ya estaba, ¡bien!
        'image_url',
        'status',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'scheduled_date' => 'date', // <--- Recomendado castear esto como fecha
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
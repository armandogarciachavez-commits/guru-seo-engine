<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str; // <--- 1. IMPORTANTE: Importamos la herramienta de texto

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain_url',
        'cms_type',
        'target_language',
        'target_city',
        'posting_frequency',
        'brand_voice',
    ];

    /**
     * MAGIA AQUÍ: Generar UUID automáticamente al crear.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            // Si el campo uuid está vacío, generamos uno nuevo
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
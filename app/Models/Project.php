<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth; // <--- 1. NUEVO: Importamos Auth para saber quién eres

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', // <--- 2. NUEVO: ¡Importante! Permitimos llenar este campo
        'name',
        'domain_url',
        'cms_type',
        'target_language',
        'target_city',
        'posting_frequency',
        'brand_voice',
    ];

    /**
     * MAGIA AQUÍ: Generar UUID y Asignar Usuario automáticamente.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            // A. Generar UUID si no existe
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }

            // B. Asignar el Usuario Actual automáticamente
            if (empty($project->user_id)) {
                $project->user_id = Auth::id(); 
            }
        });
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
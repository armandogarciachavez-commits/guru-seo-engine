<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'domain_url',
        'target_city',
        'brand_voice',
        'seo_strategy',
        'uuid', // <--- ¡CRÍTICO!

        // --- 🟢 NUEVOS CAMPOS DE CONTACTO (SOLUCIÓN A TU PROBLEMA) ---
        'phone',
        'email',
        'address',

        // --- 🔵 NUEVOS CAMPOS DE FACEBOOK (PARA LO QUE SIGUE) ---
        'facebook_page_id',
        'facebook_access_token',
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

            // B. Asignar el Usuario Actual automáticamente (si no se envió)
            if (empty($project->user_id) && Auth::check()) {
                $project->user_id = Auth::id(); 
            }
        });
    }

    // --- RELACIONES ---

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function crawlResults(): HasMany
    {
        return $this->hasMany(CrawlResult::class);
    }

    // Faltaba esta para saber quién es el dueño
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
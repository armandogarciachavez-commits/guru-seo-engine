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
        'target_language',
        'target_city',
        'brand_voice',
        'seo_strategy',
        'uuid', // <--- ¡CRÍTICO!
		'thumbnail_url',

        // --- CONFIGURACIÓN DEL MOTOR SEO ---
        'cms_type',
        'posting_frequency',
        'integration_type',
        'next_run_at',
        'niche',
        'business_name',
        'business_context',
        'target_location',
        'target_audience',
        'key_services',
        'cta_instruction',

        // --- INTEGRACIÓN WORDPRESS ---
        'wp_api_url',
        'wp_username',
        'wp_application_password',

        // --- 🟢 NUEVOS CAMPOS DE CONTACTO (SOLUCIÓN A TU PROBLEMA) ---
        'phone',
        'email',
        'address',

        // --- 🔵 NUEVOS CAMPOS DE FACEBOOK (PARA LO QUE SIGUE) ---
        'facebook_page_id',
        'facebook_access_token',

        'auto_publish',

        // --- 🟣 MONITOREO SEO (GOOGLE SEARCH CONSOLE) ---
        'gsc_property',
        'gsc_enabled',
        'gsc_min_impressions',
    ];

    protected $casts = [
        'auto_publish' => 'boolean',
        'gsc_min_impressions' => 'integer',
        // Credenciales de clientes cifradas en reposo
        'facebook_access_token' => 'encrypted',
        'wp_application_password' => 'encrypted',
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

    public function gscMetrics(): HasMany
    {
        return $this->hasMany(GscMetric::class);
    }

    public function seoOpportunities(): HasMany
    {
        return $this->hasMany(SeoOpportunity::class);
    }

    /**
     * Fecha del último escaneo SEO de este proyecto (máximo detected_at).
     */
    public function latestScanAt(): ?\Illuminate\Support\Carbon
    {
        $latest = $this->seoOpportunities()->max('detected_at');

        return $latest ? \Illuminate\Support\Carbon::parse($latest) : null;
    }

    /**
     * Oportunidades del último escaneo (el lote más reciente), ordenadas por
     * prioridad. Todas las de una corrida comparten detected_at, así que el
     * último lote es exactamente las de MAX(detected_at).
     *
     * @return \Illuminate\Support\Collection<int,\App\Models\SeoOpportunity>
     */
    public function latestScanOpportunities(): \Illuminate\Support\Collection
    {
        $latest = $this->seoOpportunities()->max('detected_at');

        if (! $latest) {
            return collect();
        }

        // Ventana de 5 min desde el máximo: las corridas nuevas comparten un solo
        // detected_at, pero los datos previos al cambio lo tienen por fila (difieren
        // en segundos). Las corridas semanales están a 7 días, así que no se mezclan.
        $windowStart = \Illuminate\Support\Carbon::parse($latest)->subMinutes(5);

        return $this->seoOpportunities()
            ->where('detected_at', '>=', $windowStart)
            ->orderByDesc('priority_score')
            ->get();
    }

    // Faltaba esta para saber quién es el dueño
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
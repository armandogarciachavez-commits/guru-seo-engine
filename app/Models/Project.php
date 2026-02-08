<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; // Importante para la relación

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
     * Relación: Un Proyecto tiene muchos Artículos.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
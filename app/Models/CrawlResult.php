<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlResult extends Model
{
    use HasFactory;

    // Esto permite guardar estos datos masivamente
    protected $fillable = [
        'project_id',
        'url',
        'status_code',
        'title',
        'h1',
        'meta_description',
        'word_count',
        'internal_links',
    ];

    protected $casts = [
        'internal_links' => 'array', // Para que JSON se convierta en Array automáticamente
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
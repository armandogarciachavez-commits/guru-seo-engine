<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WriteArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $article;
    public $timeout = 600; 

    public function __construct(Article $article)
    {
        $this->article = $article;
        // Sin forzar conexión, dejamos que el .env mande
    }

    public function handle(): void
    {
        Log::info("REDACCIÓN JOB: Iniciando para artículo ID: " . $this->article->id);

        $project = $this->article->project;
        
        if (!$project) {
            Log::error("REDACCIÓN ERROR: El artículo no tiene proyecto asociado.");
            return;
        }

        $projectUrl = $project->domain_url;
        $phone = $project->phone ?? 'No especificado';
        $email = $project->email ?? 'No especificado';
        $address = $project->address ?? 'No especificado';

        try {
            $apiKey = env('GEMINI_API_KEY');
            
            // URL LIMPIA (Aquí estaba el error de caracteres raros)
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

            $prompt = "
                ROL: Redactor SEO Experto.
                TÍTULO: '{$this->article->title}'
                KEYWORD: '{$this->article->keyword}'
                DATOS CONTACTO: {$project->name}, {$phone}, {$email}, {$address}.
                SITIO WEB: {$projectUrl}
                
                INSTRUCCIÓN: Escribe un artículo de blog completo (800 palabras) optimizado para SEO.
                FORMATO: HTML limpio (usar <h2>, <h3>, <p>, <ul>, <strong>). 
                IMPORTANTE: NO uses markdown. Devuelve solo el código HTML.
            ";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(180)
                ->post($url, [ // Usamos la variable $url limpia
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.7]
                ]);

            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$content) {
                Log::error("REDACCIÓN FALLIDA: Gemini devolvió vacío. " . $response->body());
                return;
            }

            // Limpieza
            $content = str_replace(['```html', '```'], '', $content);
            $content = trim($content);
            $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');

            $this->article->update([
                'content' => $content,
                'status' => 'generated'
            ]);

            Log::info("REDACCIÓN ÉXITO: Artículo ID {$this->article->id} guardado.");

        } catch (\Exception $e) {
            Log::error("REDACCIÓN ERROR CRÍTICO: " . $e->getMessage());
        }
    }
}
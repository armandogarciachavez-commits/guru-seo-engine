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

    // SIN VARIABLES DE CONEXIÓN - Dejamos que el .env mande.
    
    public $article;
    public $timeout = 600; 

    public function __construct(Article $article)
    {
        $this->article = $article;
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
            
            // Prompt mejorado para HTML
            $prompt = "
                ROL: Redactor SEO Experto.
                TÍTULO: '{$this->article->title}'
                KEYWORD: '{$this->article->keyword}'
                CONTEXTO CLIENTE: {$project->name}. Ubicación: {$address}.
                
                INSTRUCCIÓN: Escribe un artículo de blog completo (800 palabras) en Español.
                FORMATO: HTML puro. Usa etiquetas <h2>, <h3>, <p>, <ul>, <li>, <strong>.
                
                REGLAS IMPORTANTES:
                1. NO uses bloques de código (```html). Devuelve solo el HTML crudo.
                2. NO pongas títulos <h1>, empieza directo con <h2> o introducción <p>.
                3. Incluye un párrafo final invitando a visitar {$projectUrl}.
            ";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(180)
                ->post("[https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=)" . $apiKey, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.7]
                ]);

            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$content) {
                Log::error("REDACCIÓN FALLIDA: Gemini devolvió vacío.");
                return;
            }

            // Limpieza extra por si acaso
            $content = str_replace(['```html', '```'], '', $content);
            $content = trim($content);

            $this->article->update([
                'content' => $content,
                'status' => 'generated'
            ]);

            Log::info("REDACCIÓN ÉXITO: Artículo ID {$this->article->id} completado.");

        } catch (\Exception $e) {
            Log::error("REDACCIÓN ERROR CRÍTICO: " . $e->getMessage());
        }
    }
}
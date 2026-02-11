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
    public $timeout = 600; // 10 minutos para escribir con calma

    public function __construct(Article $article)
    {
        $this->article = $article;
    }

    public function handle(): void
    {
        Log::info("REDACCIÓN JOB: Iniciando para artículo ID: " . $this->article->id);

        $project = $this->article->project;
        
        // Verificamos que el proyecto exista (por seguridad)
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
            
            // Prompt limpio y directo
            $prompt = "
                ROL: Redactor SEO Experto.
                TÍTULO: '{$this->article->title}'
                KEYWORD: '{$this->article->keyword}'
                DATOS CONTACTO: {$project->name}, {$phone}, {$email}, {$address}.
                SITIO WEB: {$projectUrl}
                
                INSTRUCCIÓN: Escribe un artículo de blog completo (800 palabras) optimizado para SEO.
                FORMATO: HTML limpio (usar <h2>, <h3>, <p>, <ul>, <strong>). 
                IMPORTANTE: NO uses etiquetas de markdown como ```html. Devuelve solo el código HTML puro.
                
                ESTRUCTURA:
                1. Introducción enganchadora (con la keyword).
                2. Desarrollo del tema (3-4 subtítulos H2).
                3. Conclusión.
                4. LLAMADO A LA ACCIÓN (CTA): Invita a contactar a {$project->name} o visitar {$projectUrl}.
            ";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(120)
                ->post("[https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=)" . $apiKey, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.7]
                ]);

            $json = $response->json();
            $content = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$content) {
                Log::error("REDACCIÓN FALLIDA: Gemini no devolvió contenido. Respuesta: " . json_encode($json));
                return;
            }

            // Limpieza de Markdown y caracteres raros
            $content = str_replace(['```html', '```'], '', $content);
            $content = trim($content);
            
            // Forzar UTF-8 para evitar JSON Errors después
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
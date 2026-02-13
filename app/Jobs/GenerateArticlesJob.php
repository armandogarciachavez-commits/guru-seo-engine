<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Article;
use App\Services\ArticleGeneratorService; // <--- 1. IMPORTAMOS EL SERVICIO
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GenerateArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;
    public $frequency;
    public $startDate;
    public $timeout = 1200; // <--- AUMENTAMOS TIEMPO (20 mins) porque generar contenido toma tiempo

    public function __construct(Project $project, int $frequency, string $startDate)
    {
        $this->project = $project;
        $this->frequency = $frequency;
        $this->startDate = $startDate;
    }

    public function handle(): void
    {
        Log::info("PLANIFICADOR: Iniciando para " . $this->project->domain_url);

        if (empty($this->project->seo_strategy)) {
            Log::error("PLANIFICADOR ERROR: No hay Estrategia SEO guardada.");
            return;
        }

        // Instanciamos el servicio de generación (que ya incluye imágenes)
        $generatorService = new ArticleGeneratorService(); // <--- 2. INICIALIZAMOS EL ESCRITOR

        // Limpiamos la estrategia
        $strategyClean = Str::limit(strip_tags($this->project->seo_strategy), 5000); 
        $strategyClean = mb_convert_encoding($strategyClean, 'UTF-8', 'UTF-8');

        $postsA_Generar = $this->frequency * 4; 
        
        try {
            $apiKey = env('GEMINI_API_KEY');
            
            // 1. FASE DE PLANIFICACIÓN (Generar Títulos)
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;
            
            $prompt = "
                ACTUA COMO: API JSON estricta.
                CONTEXTO: {$strategyClean}
                TAREA: Generar {$postsA_Generar} títulos de artículos.
                REGLAS: RESPONDE ÚNICAMENTE CON UN ARRAY JSON: [{\"title\": \"Título\", \"keyword\": \"Keyword\"}]
            ";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(120)
                ->post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.5]
                ]);

            $jsonRaw = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
            $jsonClean = str_replace(['```json', '```'], '', $jsonRaw);
            
            $start = strpos($jsonClean, '[');
            $end = strrpos($jsonClean, ']');
            if ($start !== false && $end !== false) {
                $jsonClean = substr($jsonClean, $start, $end - $start + 1);
            }

            $plan = json_decode($jsonClean, true);

            if (!is_array($plan) || empty($plan)) {
                Log::error("PLANIFICADOR ERROR: JSON inválido.");
                return;
            }

            $currentDate = Carbon::parse($this->startDate);

            // 2. FASE DE PRODUCCIÓN (Escribir + Imagen)
            foreach ($plan as $item) {
                // Calcular fecha
                if ($this->frequency == 3) $currentDate->addDays(2); 
                elseif ($this->frequency == 5) {
                    $currentDate->addDay();
                    if ($currentDate->isWeekend()) $currentDate->addDays(2);
                } else $currentDate->addDays(3);
                
                // A. Crear el registro en BD
                $article = Article::create([
                    'project_id' => $this->project->id,
                    'title' => $item['title'] ?? 'Sin título',
                    'keyword' => $item['keyword'] ?? 'General',
                    'status' => 'draft', // Empezamos como borrador
                    'scheduled_date' => $currentDate->format('Y-m-d'),
                ]);

                // B. GENERAR CONTENIDO E IMAGEN (¡AQUÍ ESTÁ LA MAGIA!) ✨
                try {
                    // Generar Texto HTML
                    $htmlContent = $generatorService->generate($this->project, $article->keyword);
                    
                    // Buscar Imagen en Pexels (usando el método que creamos hace un momento)
                    $imageUrl = $generatorService->fetchImage($this->project);

                    // Actualizar el artículo
                    $article->update([
                        'content' => $htmlContent,
                        'thumbnail_url' => $imageUrl, // <--- Guardamos la foto
                        'status' => 'generated' // <--- Listo para que el Reloj lo publique
                    ]);

                    // Pausa de seguridad para no saturar APIs (2 segundos)
                    sleep(2);

                } catch (\Exception $e) {
                    Log::error("Error generando contenido para artículo ID {$article->id}: " . $e->getMessage());
                }
            }

            Log::info("PLANIFICADOR ÉXITO: {$postsA_Generar} artículos generados completamente con imagen.");

        } catch (\Exception $e) {
            Log::error("PLANIFICADOR CRASH: " . $e->getMessage());
        }
    }
}
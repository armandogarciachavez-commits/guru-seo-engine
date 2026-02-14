<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Article;
use App\Services\ArticleGeneratorService; // <--- Importamos el Servicio
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
    public $timeout = 1200; // 20 minutos de espera (necesario para generar imágenes y texto)

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

        // Instanciamos el servicio (que ahora sabe buscar en Pexels)
        $generatorService = new ArticleGeneratorService();

        // Limpieza de datos
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

            // Validación de seguridad por si Gemini falla
            if ($response->failed()) {
                Log::error("PLANIFICADOR ERROR: Falló la conexión con Gemini. " . $response->body());
                return;
            }

            $jsonRaw = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
            $jsonClean = str_replace(['```json', '```'], '', $jsonRaw);
            
            // Extracción segura del JSON
            $start = strpos($jsonClean, '[');
            $end = strrpos($jsonClean, ']');
            if ($start !== false && $end !== false) {
                $jsonClean = substr($jsonClean, $start, $end - $start + 1);
            }

            $plan = json_decode($jsonClean, true);

            if (!is_array($plan) || empty($plan)) {
                Log::error("PLANIFICADOR ERROR: JSON inválido recibido de la IA.");
                return;
            }

            $currentDate = Carbon::parse($this->startDate);

            // 2. FASE DE PRODUCCIÓN (Escribir Texto + Buscar Imagen)
            foreach ($plan as $item) {
                // Calcular fecha
                if ($this->frequency == 3) $currentDate->addDays(2); 
                elseif ($this->frequency == 5) {
                    $currentDate->addDay();
                    if ($currentDate->isWeekend()) $currentDate->addDays(2);
                } else $currentDate->addDays(3);
                
                // A. Crear el registro "Borrador"
                $article = Article::create([
                    'project_id' => $this->project->id,
                    'title' => $item['title'] ?? 'Sin título',
                    'keyword' => $item['keyword'] ?? 'General',
                    'status' => 'draft',
                    'scheduled_date' => $currentDate->format('Y-m-d'),
                ]);

                // B. MAGIA: Generar HTML + Imagen Pexels
                try {
                    // 1. Generar Texto
                    $htmlContent = $generatorService->generate($this->project, $article->keyword);
                    
                    // 2. Buscar Imagen (Pexels)
                    // Este método usa el "Niche" del proyecto para buscar la foto exacta
                    $imageUrl = $generatorService->fetchImage($this->project);

                    // 3. Guardar todo y dejar listo para publicar
                    $article->update([
                        'content' => $htmlContent,
                        'thumbnail_url' => $imageUrl, // Guardamos la URL de Pexels
                        'status' => 'generated' // Estado listo para el "Reloj" automático
                    ]);

                    // Pausa técnica para respetar límites de API
                    sleep(2);

                } catch (\Exception $e) {
                    Log::error("Error generando contenido para artículo ID {$article->id}: " . $e->getMessage());
                }
            }

            Log::info("PLANIFICADOR ÉXITO: {$postsA_Generar} artículos creados con imagen.");

        } catch (\Exception $e) {
            Log::error("PLANIFICADOR CRASH: " . $e->getMessage());
        }
    }
}
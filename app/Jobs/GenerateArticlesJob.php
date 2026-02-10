<?php

namespace App\Jobs;

use App\Models\Project;
use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GenerateArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;
    public $frequency;
    public $startDate;
    public $timeout = 600; // 10 minutos

    public function __construct(Project $project, int $frequency, string $startDate)
    {
        $this->project = $project;
        $this->frequency = $frequency;
        $this->startDate = $startDate;
    }

    public function handle(): void
    {
        Log::info("ARTÍCULOS: Iniciando generación para " . $this->project->name);

        // 1. LIMPIEZA DE CARACTERES RAROS (Esto arregla el error UTF-8)
        $strategyClean = mb_convert_encoding($this->project->seo_strategy, 'UTF-8', 'UTF-8');

        if (empty($strategyClean)) {
            Log::error("ARTÍCULOS: Estrategia vacía o corrupta.");
            return;
        }

        $postsA_Generar = $this->frequency * 4; // 1 mes de contenido
        $fechaInicio = Carbon::parse($this->startDate);

        try {
            $apiKey = env('GEMINI_API_KEY');
            
            // 2. Prompt para Gemini
            $prompt = "
                ACTUA COMO: API JSON. 
                CONTEXTO ESTRATÉGICO: {$strategyClean}
                
                TAREA: Generar una lista de {$postsA_Generar} ideas de artículos para blog basados en la estrategia.
                
                REGLAS:
                - Devuelve SOLO un array JSON válido.
                - Sin markdown, sin explicaciones.
                
                FORMATO JSON:
                [
                    {\"title\": \"Título atractivo\", \"keyword\": \"Palabra clave\", \"intention\": \"Informativa/Comercial\"}
                ]
            ";

            // 3. Llamada a la API
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(120)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.7]
                ]);

            $jsonText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
            
            // Limpiar Markdown (```json ... ```)
            $jsonText = str_replace(['```json', '```', '```'], '', $jsonText);
            
            $plan = json_decode($jsonText, true);

            if (!is_array($plan)) {
                Log::error("ARTÍCULOS: La IA no devolvió un JSON válido. Respuesta: " . substr($jsonText, 0, 100));
                return;
            }

            // 4. Crear los artículos en la BD
            $currentDate = $fechaInicio->copy();

            foreach ($plan as $item) {
                // Cálculo de fechas
                if ($this->frequency == 3) $currentDate->addDays(2); 
                elseif ($this->frequency == 5) {
                    $currentDate->addDay();
                    if ($currentDate->isWeekend()) $currentDate->addDays(2);
                } else $currentDate->addDays(3);
                
                Article::create([
                    'project_id' => $this->project->id,
                    'title' => $item['title'] ?? 'Sin título',
                    'keyword' => $item['keyword'] ?? 'General',
                    'status' => 'pending', 
                    'scheduled_date' => $currentDate->format('Y-m-d'),
                ]);
            }

            Log::info("ARTÍCULOS: Éxito. {$postsA_Generar} artículos creados.");

        } catch (\Exception $e) {
            Log::error("ARTÍCULOS ERROR: " . $e->getMessage());
        }
    }
}
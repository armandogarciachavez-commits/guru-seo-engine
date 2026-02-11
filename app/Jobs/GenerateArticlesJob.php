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
use Illuminate\Support\Str;
use Carbon\Carbon;

class GenerateArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // --- ❌ BORRAMOS LA LÍNEA QUE CAUSÓ EL ERROR ---
    // public $connection = 'database'; 
    
    public $project;
    public $frequency;
    public $startDate;
    public $timeout = 600; 

    public function __construct(Project $project, int $frequency, string $startDate)
    {
        $this->project = $project;
        $this->frequency = $frequency;
        $this->startDate = $startDate;

        // --- ✅ LA PONEMOS AQUÍ (FORMA CORRECTA) ---
        $this->onConnection('database');
    }

    public function handle(): void
    {
        Log::info("PROGRAMAR JOB: Iniciando para " . $this->project->domain_url);

        if (empty($this->project->seo_strategy)) {
            Log::error("PROGRAMAR ERROR: No hay Estrategia SEO guardada.");
            return;
        }

        // Limpiamos la estrategia
        $strategyClean = Str::limit(strip_tags($this->project->seo_strategy), 5000); 
        $strategyClean = mb_convert_encoding($strategyClean, 'UTF-8', 'UTF-8');

        $postsA_Generar = $this->frequency * 4; 
        
        try {
            $apiKey = env('GEMINI_API_KEY');
            
            $prompt = "
                ACTUA COMO: API JSON estricta.
                CONTEXTO: {$strategyClean}
                TAREA: Generar {$postsA_Generar} títulos de artículos.
                REGLAS: RESPONDE ÚNICAMENTE CON UN ARRAY JSON: [{\"title\": \"Título\", \"keyword\": \"Keyword\"}]
            ";

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(120)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.5]
                ]);

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
                Log::error("PROGRAMAR ERROR: JSON inválido.");
                return;
            }

            $currentDate = Carbon::parse($this->startDate);

            foreach ($plan as $item) {
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

            Log::info("PROGRAMAR ÉXITO: {$postsA_Generar} artículos creados.");

        } catch (\Exception $e) {
            Log::error("PROGRAMAR CRASH: " . $e->getMessage());
        }
    }
}
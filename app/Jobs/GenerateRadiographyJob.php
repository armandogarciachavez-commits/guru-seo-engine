<?php

namespace App\Jobs;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateRadiographyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $project;
    public $competitors; // Para recibir lo que escribiste en el formulario
    public $timeout = 600; // 10 minutos de vida

    public function __construct(Project $project, ?string $competitors)
    {
        $this->project = $project;
        $this->competitors = $competitors ?? 'N/A';
    }

    public function handle(): void
    {
        Log::info("RADIOGRAFÍA: Iniciando job para " . $this->project->domain_url);

        // 1. Recopilar datos del rastreo (Misma lógica que tenías)
        $results = $this->project->crawlResults;

        if ($results->isEmpty()) {
            Log::warning("RADIOGRAFÍA: Cancelada. No hay datos de rastreo.");
            return;
        }

        // Tomamos los 40 mejores resultados status 200
        $siteMapData = $results->where('status_code', 200)
            ->take(40)
            ->map(fn($r) => "URL: {$r->url} | H1: {$r->h1} | Title: {$r->title}")
            ->implode("\n");

        $ciudad = $this->project->target_city ?? 'Local';

        try {
            $apiKey = env('GEMINI_API_KEY');
            
            // 2. EL PROMPT MAESTRO (Tu prompt original)
            $prompt = "
                ROL: Consultor de Marketing Digital de Alto Nivel.
                TAREA: Crear Auditoría Estratégica para {$this->project->domain_url}.
                CIUDAD OBJETIVO: {$ciudad}
                DATOS RASTREADOS: {$siteMapData}
                COMPETENCIA: {$this->competitors}
                
                INSTRUCCIONES DE FORMATO (ESTRICTO):
                - NO devuelvas bloques de texto planos.
                - Usa TABLAS HTML para comparar datos.
                - Usa 'Cards' o recuadros para los hallazgos importantes.
                - Usa clases de Tailwind CSS para dar estilo (bg-gray-100, p-4, rounded, text-blue-600, etc.).

                ESTRUCTURA REQUERIDA:
                1. <div class='bg-blue-50 p-4 rounded'><h2>🔍 Diagnóstico de Identidad</h2>...</div>
                2. Tabla de Brechas vs Competencia (Columnas: Tema | Estado Actual | Oportunidad).
                3. Tabla de Keywords de Oro (Columnas: Keyword | Intención | Prioridad).
                4. <div class='grid grid-cols-2 gap-4'>... (Tarjetas para los Pilares de Contenido) ...</div>
            ";

            // 3. Llamada a Gemini
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(120)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.5]
                ]);

            $aiReport = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if ($aiReport) {
                // Limpiar markdown
                $aiReport = str_replace(['```html', '```'], '', $aiReport);

                // 4. Guardar en Base de Datos
                $this->project->forceFill(['seo_strategy' => $aiReport])->save();
                
                Log::info("RADIOGRAFÍA: Completada y guardada.");
            } else {
                Log::error("RADIOGRAFÍA: La IA devolvió respuesta vacía.");
            }

        } catch (\Exception $e) {
            Log::error("RADIOGRAFÍA ERROR: " . $e->getMessage());
        }
    }
}
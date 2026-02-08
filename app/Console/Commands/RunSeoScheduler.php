<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\Article;
use App\Services\WordPressService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RunSeoScheduler extends Command
{
    protected $signature = 'app:run-seo-scheduler';
    protected $description = 'Genera contenido dinámico leyendo la base de datos';

    public function handle(WordPressService $wpService)
    {
        $this->info('🚀 Iniciando Motor Multi-Cliente (Gemini 2.0)...');

        $apiKey = env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            $this->error('🚨 ERROR: Falta GEMINI_API_KEY en .env');
            return;
        }

        // 1. BUSCAMOS PROYECTOS LISTOS
        // Que tengan fecha pendiente (o nula) Y que tengan el nicho configurado
        $projects = Project::whereNotNull('niche')
                          ->whereNotNull('business_name')
                          ->where(function ($query) {
                              $query->where('next_run_at', '<=', now())
                                    ->orWhereNull('next_run_at');
                          })
                          ->get();

        if ($projects->isEmpty()) {
            $this->info("💤 No hay proyectos pendientes con configuración completa.");
            return;
        }

        foreach ($projects as $project) {
            $this->info("⚙️ Procesando Cliente: {$project->business_name}");
            
            // DATOS DINÁMICOS (Leídos de la BD)
            $nicho = $project->niche;
            $nombreNegocio = $project->business_name;

            // PROMPT INTELIGENTE
            $prompt = "Eres un redactor experto para '{$nombreNegocio}', una empresa líder en {$nicho}. 
            
            Escribe un artículo de blog persuasivo y útil (300 palabras) sobre un producto o servicio clave de este nicho.
            
            REGLAS OBLIGATORIAS:
            1. Título atractivo y profesional.
            2. Usa formato HTML limpio (<h2>, <p>, <ul>).
            3. En el contenido, menciona que '{$nombreNegocio}' es la mejor opción en la región.
            4. CIERRE: Invita explícitamente a contactar a '{$nombreNegocio}' para una cotización.

            Responde SOLO con este JSON exacto:
            {
                \"title\": \"Título del artículo\",
                \"content\": \"<p>Contenido HTML aquí...</p>\"
            }";

            // LLAMADA A GEMINI 2.0 FLASH
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}";

            try {
                $response = Http::post($url, [
                    'contents' => [['parts' => [['text' => $prompt]]]]
                ]);
            } catch (\Exception $e) {
                $this->error("❌ Error de conexión: " . $e->getMessage());
                continue;
            }

            if ($response->successful()) {
                $rawText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $cleanJson = str_replace(['```json', '```'], '', $rawText);
                $data = json_decode($cleanJson, true);

                if (!$data) {
                    $this->error("❌ JSON inválido de la IA.");
                    continue;
                }

                $title = $data['title'];
                $content = $data['content'];

                // Guardar
                $article = Article::create([
                    'project_id' => $project->id,
                    'keyword' => 'Auto: ' . Str::limit($title, 15),
                    'title' => $title,
                    'slug' => Str::slug($title) . '-' . time(),
                    'html_content' => $content,
                    'status' => 'published'
                ]);

                $this->info("✅ Artículo Generado: {$title}");

                // Publicar
                if ($project->integration_type === 'wordpress') {
                    $wpService->publish($article, $project);
                    
                    // Reprogramar para mañana
                    $project->update(['next_run_at' => now()->addDay()]);
                    $this->info("📅 Próxima ejecución: Mañana");
                }

            } else {
                $this->error("❌ Error Google: " . $response->body());
            }
        }
    }
}
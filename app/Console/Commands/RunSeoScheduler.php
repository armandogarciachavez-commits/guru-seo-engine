<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Project;
use App\Models\Article;
use App\Services\WordPressService;
use App\Jobs\PostToFacebookJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RunSeoScheduler extends Command
{
    protected $signature = 'app:run-seo-scheduler';
    protected $description = 'Genera contenido dinámico leyendo la base de datos';

    public function handle(WordPressService $wpService)
    {
        $this->info('🚀 Iniciando Motor Multi-Cliente (OpenRouter)...');

        $apiKey = config('services.openrouter.api_key');
        if (empty($apiKey)) {
            $this->error('🚨 ERROR: Falta OPENROUTER_API_KEY en .env');
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

            // LLAMADA A LA IA (OpenRouter, servicio único)
            $data = (new \App\Services\OpenRouterService)->generateJson($prompt, [
                'system' => 'Responde exclusivamente con JSON válido.',
            ]);

            if (! $data || empty($data['title']) || empty($data['content'])) {
                $this->error("❌ JSON inválido o sin respuesta de la IA.");
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
            }

            // Compartir en Facebook (el job no hace nada si el proyecto no tiene FB configurado)
            PostToFacebookJob::dispatch($article->fresh())->onConnection('database');

            // Reprogramar para mañana (todos los tipos de integración,
            // si no, el proyecto se procesa en cada corrida y genera artículos sin límite)
            $project->update(['next_run_at' => now()->addDay()]);
            $this->info("📅 Próxima ejecución: Mañana");
        }
    }
}
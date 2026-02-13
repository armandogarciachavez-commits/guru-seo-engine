<?php

namespace App\Jobs;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostToFacebookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $article;

    /**
     * Recibimos el artículo recién publicado.
     */
    public function __construct(Article $article)
    {
        $this->article = $article;
    }

    public function handle()
    {
        // 1. Obtener el proyecto
        $project = $this->article->project;

        // 2. Verificar si tiene Facebook conectado
        if (!$project->facebook_page_id || !$project->facebook_access_token) {
            // Si no configuró Facebook, no hacemos nada y terminamos.
            return;
        }

        // 3. Construir el Mensaje (Copy)
        // Usamos los primeros 200 caracteres del contenido como "teaser"
        $resumen = mb_substr(strip_tags($this->article->content), 0, 200) . "...";
        
        $mensaje = "✨ " . $this->article->title . "\n\n" . 
                   $resumen . "\n\n" . 
                   "👇 Lee la nota completa aquí:";

        // 4. Construir el Enlace
        // Facebook necesita la URL para generar la tarjeta clicable
        // Asumimos que la URL base del proyecto es donde vive el blog.
        // Ej: https://elviaconsulting.com
        $link = rtrim($project->domain_url, '/');

        // OJO: Si tienes una estructura específica en WordPress, ajústalo aquí.
        // Ej: $link = $project->domain_url . '/blog'; 

        // 5. Enviar a Facebook (Graph API)
        // Usamos el endpoint /feed porque es solo texto+link
        $response = Http::post("https://graph.facebook.com/{$project->facebook_page_id}/feed", [
            'message'      => $mensaje,
            'link'         => $link,
            'access_token' => $project->facebook_access_token,
        ]);

        // 6. Registrar resultado en el Log de Laravel
        if ($response->successful()) {
            Log::info("✅ Publicado en Facebook: " . $this->article->title);
        } else {
            Log::error("❌ Error Facebook: " . $response->body());
        }
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Project;

class WordPressService
{
    public function publish($article, Project $project)
    {
        echo "\n📢 [DEBUG] Iniciando servicio de publicación...\n";

        // 1. Limpiamos la URL para evitar errores de doble barra
        $baseUrl = rtrim($project->wp_api_url, '/');
        
        // Si la URL guardada NO termina en wp-json, avisa pero intenta arreglarlo
        if (!str_contains($baseUrl, 'wp-json')) {
             echo "⚠️ [ALERTA] La URL en base de datos no parece tener /wp-json. Intentando añadirlo...\n";
             $baseUrl .= '/wp-json';
        }

        // 2. Construimos la ruta final exacta
        // NOTA: Muchos plugins de seguridad cambian esto, pero el estándar es /wp/v2/posts
        $endpoint = $baseUrl . '/wp/v2/posts';
        
        echo "🔗 [DEBUG] URL Destino: " . $endpoint . "\n";

        try {
            $response = Http::withBasicAuth($project->wp_username, $project->wp_application_password)
                ->post($endpoint, [
                    'title'   => $article->title,
                    'content' => $article->html_content, // Asegúrate que tu modelo use este nombre
                    'status'  => 'publish', // Intentamos publicar directo
                    'slug'    => $article->slug,
                ]);

            echo "A [DEBUG] Respuesta del Servidor Código: " . $response->status() . "\n";

            if ($response->successful()) {
                echo "✅ [EXITO] Post creado con ID: " . $response->json()['id'] . "\n";
                return $response->json();
            } else {
                echo "❌ [ERROR] El servidor respondió: " . $response->body() . "\n";
                return false;
            }

        } catch (\Exception $e) {
            echo "🔥 [FATAL] Error de conexión: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

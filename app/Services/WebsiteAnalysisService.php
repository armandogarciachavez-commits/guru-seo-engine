<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebsiteAnalysisService
{
    /**
     * Analiza una URL y extrae información básica para el SEO.
     *
     * @param string $url
     * @return array
     */
    public function analyze(string $url): array
    {
        // 1. Validar URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \Exception("La URL no es válida.");
        }

        try {
            // 2. Intentar obtener el contenido HTML de la página
            // Usamos un User-Agent para que no nos bloqueen pensando que somos un robot
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->timeout(10)->get($url);

            if ($response->failed()) {
                throw new \Exception("No se pudo conectar al sitio web (Código: " . $response->status() . ")");
            }

            $html = $response->body();

            // 3. Extraer datos básicos usando expresiones regulares (Regex)
            // Título
            preg_match('/<title>(.*?)<\/title>/is', $html, $matchesTitle);
            $title = $matchesTitle[1] ?? '';

            // Meta Description (El tesoro del contexto)
            preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\'](.*?)["\'][^>]*>/i', $html, $matchesDesc);
            $description = $matchesDesc[1] ?? '';

            // Meta Keywords (Si existen)
            preg_match('/<meta[^>]*name=["\']keywords["\'][^>]*content=["\'](.*?)["\'][^>]*>/i', $html, $matchesKeys);
            $keywords = $matchesKeys[1] ?? '';

            // Limpieza básica
            $title = Str::limit(strip_tags(trim($title)), 255);
            $description = Str::limit(strip_tags(trim($description)), 500);

            // 4. Preparar la respuesta para el formulario
            // Si no hay descripción, usamos el título como contexto
            $contexto = !empty($description) ? $description : "Sitio web de $title. Se requiere análisis manual adicional.";

            return [
                'business_name' => $title, // Usamos el título como nombre comercial sugerido
                'business_context' => $contexto,
                'target_audience' => 'Clientes interesados en ' . ($title ?: 'este servicio'), // Sugerencia genérica
                'target_location' => 'Manzanillo, Colima', // Default por tu zona, cámbialo si quieres
                'key_services' => $keywords ?: 'Servicios generales de ' . $title,
                'cta_instruction' => 'Contáctanos a través de nuestro sitio web ' . $url,
                'brand_voice' => 'Profesional, Informativo y Cercano', // Default seguro
            ];

        } catch (\Exception $e) {
            // Si algo falla, lanzamos el error para que Filament lo muestre en la notificación roja
            throw new \Exception("Error analizando el sitio: " . $e->getMessage());
        }
    }
}

<?php

namespace App\Services;

use App\Models\Project;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Str;
// 👇 IMPORTANTE: Importamos nuestro buscador de fotos
use App\Services\PexelsService; 

class ArticleGeneratorService
{
    /**
     * Genera un artículo SEO optimizado usando la identidad de marca del proyecto.
     *
     * @param Project $project
     * @param string|null $keyword
     * @return string
     */
    public function generate(Project $project, ?string $keyword = null): string
    {
        // 1. Si no hay keyword, usamos el servicio principal como tema
        $topic = $keyword ?? $project->key_services ?? 'Servicios de ' . $project->business_name;

        // 2. Construimos el Prompt (Las instrucciones para la IA)
        $prompt = $this->buildPrompt($project, $topic);

        try {
            // 3. Llamamos a Gemini (Modelo Flash para rapidez y calidad)
            $result = Gemini::generativeModel('gemini-2.0-flash')->generateContent($prompt);
            
            // Obtenemos el texto crudo
            $rawContent = $result->text();

            // --- LIMPIEZA AUTOMÁTICA ---
            // Eliminamos las etiquetas de código que la IA suele poner (```html y ```)
            $cleanContent = str_replace(['```html', '```'], '', $rawContent);
            
            // Devolvemos el contenido limpio
            return $cleanContent;

        } catch (\Exception $e) {
            // Si falla, devolvemos un mensaje de error limpio para que no rompa la web
            return "<h1>Error al generar contenido</h1><p>Ocurrió un error conectando con la IA: " . $e->getMessage() . "</p>";
        }
    }

    /**
     * 📸 NUEVO MÉTODO: Busca una imagen relacionada en Pexels.
     * Usa la lógica de prioridad: Nicho > Estrategia > Ciudad > General.
     */
    public function fetchImage(Project $project): ?string
    {
        // 1. Decidir qué palabra clave buscar
        // Si tiene 'niche' (Ej: Dentista), es lo mejor. 
        // Si no, usamos la estrategia SEO o la ciudad.
        $searchQuery = $project->niche 
                    ?? $project->seo_strategy 
                    ?? $project->target_city 
                    ?? 'Negocios';

        // 2. Llamar al servicio de Pexels
        // (Si el servicio devuelve null, usamos una imagen de respaldo aquí mismo)
        $imageUrl = PexelsService::search($searchQuery);

        if (!$imageUrl) {
            // Imagen de respaldo segura (Oficina genérica)
            return 'https://images.pexels.com/photos/3183150/pexels-photo-3183150.jpeg';
        }

        return $imageUrl;
    }

    /**
     * Construye el prompt detallado usando los campos de la base de datos.
     */
    private function buildPrompt(Project $project, string $topic): string
    {
        // Limpiamos los datos para evitar nulos
        $context = $project->business_context ?? 'Empresa profesional';
        $location = $project->target_location ?? 'México';
        $audience = $project->target_audience ?? 'Clientes potenciales';
        $voice = $project->brand_voice ?? 'Profesional y cercano';
        $services = $project->key_services ?? 'Servicios generales';
        $cta = $project->cta_instruction ?? 'Contáctanos para más información';
        $lang = $project->target_language ?? 'es-MX';
        
        // Agregamos los datos de contacto al prompt para que la IA los use si quiere
        $contactInfo = "";
        if ($project->phone) $contactInfo .= "Tel: {$project->phone}. ";
        if ($project->email) $contactInfo .= "Email: {$project->email}. ";
        if ($project->address) $contactInfo .= "Dirección: {$project->address}. ";

        return <<<EOT
Actúa como un Redactor SEO Experto y Copywriter Senior especializado en {$location}.

TU MISIÓN:
Escribir un artículo de blog altamente persuasivo y optimizado para SEO sobre el tema: "{$topic}".

DATOS DE LA EMPRESA (IDENTIDAD DE MARCA):
- Nombre: {$project->business_name}
- Quiénes Somos (Contexto): {$context}
- Ubicación (SEO Local): {$location}
- Público Objetivo: {$audience}
- Servicios Clave a Vender: {$services}
- Tono de Voz: {$voice}
- Datos de Contacto: {$contactInfo}

REQUISITOS DEL ARTÍCULO:
1. **Formato HTML Puro:** Usa solo etiquetas <h1>, <h2>, <h3>, <p>, <ul>, <li>, <strong>. NO uses markdown (```), ni <html>, ni <body>.
2. **Estructura SEO:**
   - Un H1 atractivo que incluya la palabra clave "{$topic}".
   - Al menos 3 subtítulos H2 que resuelvan dudas del {$audience}.
   - Párrafos cortos y fáciles de leer en móvil.
3. **Estrategia de Ventas:**
   - Empieza con un "Gancho" que hable del problema del cliente.
   - Desarrolla el contenido educando al cliente.
   - **IMPORTANTE:** Menciona sutilmente que {$project->business_name} es la solución en {$location}.
4. **Cierre (CTA):**
   - Termina con un párrafo fuerte invitando a la acción.
   - Usa esta instrucción específica: {$cta}.
   - Si es relevante, incluye los datos de contacto al final.

IDIOMA DE SALIDA: Español ({$lang})

Empieza a escribir el contenido HTML ahora:
EOT;
    }
}
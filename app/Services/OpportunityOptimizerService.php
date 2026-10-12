<?php

namespace App\Services;

use App\Models\SeoOpportunity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fase 4 — Bucle cerrado: para cada oportunidad de quick-win, la IA (Gemini)
 * genera un title y meta description optimizados con la keyword, listos para
 * aplicar en la página. Reutiliza la misma integración de Gemini que el motor
 * de contenido (config gemini.api_key, gemini-2.0-flash, header x-goog-api-key).
 */
class OpportunityOptimizerService
{
    protected string $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    /**
     * Genera sugerencias de metadatos para una oportunidad y las guarda en
     * ai_suggestions. Devuelve el arreglo de sugerencias o null si falla.
     *
     * @return array{title:string,meta_description:string,notes:string}|null
     */
    public function optimize(SeoOpportunity $opportunity, bool $persist = true): ?array
    {
        $apiKey = config('gemini.api_key');

        if (empty($apiKey)) {
            Log::error('[SEO Optimizer] GEMINI_API_KEY no configurada.');
            return null;
        }

        $project = $opportunity->project;
        $business = $project->business_name ?? $project->name ?? 'el negocio';
        $city = $project->target_city ?? $project->target_location ?? '';

        $prompt = <<<PROMPT
ACTUA COMO: API JSON estricta de SEO.

CONTEXTO:
- Negocio: {$business}
- Ciudad/Región: {$city}
- Keyword objetivo: "{$opportunity->query}"
- Página que rankea: {$opportunity->page}
- Situación: la página aparece en posición {$opportunity->position} de Google con
  buenas impresiones pero CTR muy bajo. El title/meta description no está atrayendo clics.

TAREA:
Genera un <title> y una <meta description> optimizados para subir el CTR de esa keyword.

REGLAS:
- title: máximo 60 caracteres, incluye la keyword de forma natural y un gancho.
- meta_description: 140-155 caracteres, incluye la keyword, un beneficio claro y un llamado a la acción.
- Español neutro. Sin comillas tipográficas. No inventes datos (precios, premios).
- notes: 1 frase explicando por qué este title/meta mejoraría el CTR.

RESPONDE ÚNICAMENTE CON ESTE JSON (sin markdown, sin backticks):
{"title": "...", "meta_description": "...", "notes": "..."}
PROMPT;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
                ->timeout(60)
                ->post($this->endpoint, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.6],
                ]);

            if ($response->failed()) {
                Log::error('[SEO Optimizer] Gemini falló: ' . $response->body());
                return null;
            }

            $raw = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $clean = str_replace(['```json', '```'], '', $raw);

            $start = strpos($clean, '{');
            $end = strrpos($clean, '}');
            if ($start !== false && $end !== false) {
                $clean = substr($clean, $start, $end - $start + 1);
            }

            $data = json_decode($clean, true);

            if (! is_array($data) || empty($data['title']) || empty($data['meta_description'])) {
                Log::error('[SEO Optimizer] JSON inválido de Gemini: ' . $raw);
                return null;
            }

            $suggestions = [
                'title' => trim($data['title']),
                'meta_description' => trim($data['meta_description']),
                'notes' => trim($data['notes'] ?? ''),
            ];

            if ($persist) {
                $opportunity->update([
                    'ai_suggestions' => $suggestions,
                    'status' => $opportunity->status === SeoOpportunity::STATUS_NEW
                        ? SeoOpportunity::STATUS_IN_PROGRESS
                        : $opportunity->status,
                ]);
            }

            return $suggestions;
        } catch (\Throwable $e) {
            Log::error('[SEO Optimizer] Error: ' . $e->getMessage());
            return null;
        }
    }
}

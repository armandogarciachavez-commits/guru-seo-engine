<?php

namespace App\Services;

use App\Models\SeoOpportunity;
use Illuminate\Support\Facades\Log;

/**
 * Fase 4 — Bucle cerrado: para cada oportunidad de quick-win, la IA genera un
 * title y meta description optimizados con la keyword, listos para aplicar en
 * la página. Usa el servicio único OpenRouterService (mismo motor que el
 * resto del sistema).
 */
class OpportunityOptimizerService
{
    /**
     * Genera sugerencias de metadatos para una oportunidad y las guarda en
     * ai_suggestions. Devuelve el arreglo de sugerencias o null si falla.
     *
     * @return array{title:string,meta_description:string,notes:string}|null
     */
    public function optimize(SeoOpportunity $opportunity, bool $persist = true): ?array
    {
        // No pisar la sugerencia de una oportunidad ya aplicada o descartada.
        if (in_array($opportunity->status, [SeoOpportunity::STATUS_APPLIED, SeoOpportunity::STATUS_DISMISSED], true)) {
            Log::info('[SEO Optimizer] Oportunidad '.$opportunity->id." en estado {$opportunity->status}; se omite.");

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
  buenas impresiones pero CTR muy bajo. El title/meta description no atrae clics.

TAREA:
Genera un title y una meta description optimizados para subir el CTR de esa keyword.

REGLAS:
- title: máximo 60 caracteres, incluye la keyword de forma natural y un gancho.
- meta_description: 140-155 caracteres, incluye la keyword, un beneficio claro y un llamado a la acción.
- Español neutro. Sin comillas tipográficas. No inventes datos (precios, premios).
- notes: 1 frase explicando por qué este title/meta mejoraría el CTR.

RESPONDE ÚNICAMENTE CON ESTE JSON (sin markdown, sin backticks):
{"title": "...", "meta_description": "...", "notes": "..."}
PROMPT;

        $data = (new OpenRouterService)->generateJson($prompt, [
            'system' => 'Eres un experto SEO. Respondes exclusivamente con JSON válido.',
            'temperature' => 0.6,
            'timeout' => 60,
        ]);

        if (! is_array($data) || empty($data['title']) || empty($data['meta_description'])) {
            Log::error('[SEO Optimizer] Respuesta inválida de la IA para oportunidad '.$opportunity->id);

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
    }
}

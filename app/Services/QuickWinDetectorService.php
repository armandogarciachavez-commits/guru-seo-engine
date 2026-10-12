<?php

namespace App\Services;

use App\Models\GscMetric;
use App\Models\Project;
use App\Models\SeoOpportunity;
use Illuminate\Support\Carbon;

/**
 * Detecta oportunidades de "quick win" (striking distance) a partir de los
 * datos de Google Search Console guardados en gsc_metrics.
 *
 * Criterio: keyword en posición media entre MIN y MAX (por defecto 5–15) con
 * impresiones suficientes y CTR por debajo del esperado para SU posición actual
 * (el snippet rinde menos de lo normal → metadato mejorable). El potencial de
 * clics se estima contra la posición objetivo y define la prioridad.
 */
class QuickWinDetectorService
{
    public float $minPosition = 5.0;

    public float $maxPosition = 15.0;

    public const DEFAULT_MIN_IMPRESSIONS = 10;

    /**
     * Impresiones mínimas en la ventana para considerar la oportunidad.
     * Cada proyecto puede sobreescribirlo con projects.gsc_min_impressions.
     */
    public int $minImpressions = self::DEFAULT_MIN_IMPRESSIONS;

    /** Posición "objetivo" alcanzable con optimización, para estimar el potencial. */
    public float $targetPosition = 3.0;

    /**
     * Analiza el lote más reciente de métricas de un proyecto y crea/actualiza
     * las oportunidades detectadas. Devuelve las oportunidades (frescas) ordenadas
     * por prioridad descendente.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectForProject(Project $project, bool $persist = true): array
    {
        // Lote más reciente de métricas de este proyecto.
        $latestCapture = GscMetric::where('project_id', $project->id)->max('captured_at');

        if (! $latestCapture) {
            return [];
        }

        $metrics = GscMetric::where('project_id', $project->id)
            ->where('captured_at', $latestCapture)
            ->whereNotNull('query')
            ->get();

        $minImpressions = $project->gsc_min_impressions ?: $this->minImpressions;
        $targetCtr = $this->expectedCtr($this->targetPosition);
        $opportunities = [];

        foreach ($metrics as $m) {
            if ($m->position < $this->minPosition || $m->position > $this->maxPosition) {
                continue;
            }
            if ($m->impressions < $minImpressions) {
                continue;
            }

            if ((float) $m->ctr >= $this->expectedCtr($m->position)) {
                continue; // ya rinde igual o mejor que lo normal para su posición
            }

            $gain = $targetCtr - (float) $m->ctr;

            $potentialClicks = (int) round($m->impressions * $gain);
            if ($potentialClicks < 1) {
                continue;
            }

            // Prioridad: potencial de clics, con un pequeño peso por cercanía al top.
            $priority = round($potentialClicks * (1 + (15 - $m->position) / 30), 2);

            $opportunities[] = [
                'project_id' => $project->id,
                'query' => $m->query,
                'page' => $m->page,
                'clicks' => $m->clicks,
                'impressions' => $m->impressions,
                'ctr' => $m->ctr,
                'position' => $m->position,
                'potential_clicks' => $potentialClicks,
                'priority_score' => $priority,
                'window_from' => $m->window_from,
                'window_to' => $m->window_to,
            ];
        }

        // Ordenar por prioridad descendente.
        usort($opportunities, fn ($a, $b) => $b['priority_score'] <=> $a['priority_score']);

        if ($persist) {
            foreach ($opportunities as $opp) {
                SeoOpportunity::updateOrCreate(
                    [
                        'project_id' => $opp['project_id'],
                        'query' => $opp['query'],
                        'page' => $opp['page'],
                    ],
                    array_merge($opp, [
                        // No pisar un estado de trabajo manual si ya existía.
                        'detected_at' => Carbon::now(),
                    ])
                );
            }
        }

        return $opportunities;
    }

    /**
     * CTR esperado (fracción 0-1) para una posición dada.
     * Curva aproximada basada en estudios públicos de CTR orgánico.
     */
    public function expectedCtr(float $position): float
    {
        $curve = [
            1 => 0.280, 2 => 0.155, 3 => 0.110, 4 => 0.080, 5 => 0.060,
            6 => 0.048, 7 => 0.040, 8 => 0.034, 9 => 0.030, 10 => 0.026,
            11 => 0.022, 12 => 0.019, 13 => 0.016, 14 => 0.014, 15 => 0.012,
        ];

        $p = (int) round($position);
        if ($p < 1) {
            $p = 1;
        }
        if ($p > 15) {
            return 0.010;
        }

        return $curve[$p] ?? 0.010;
    }
}

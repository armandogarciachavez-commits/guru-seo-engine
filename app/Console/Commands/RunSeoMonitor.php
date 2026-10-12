<?php

namespace App\Console\Commands;

use App\Jobs\SyncSearchConsoleJob;
use App\Models\Project;
use App\Models\SeoOpportunity;
use App\Services\OpportunityOptimizerService;
use App\Services\QuickWinDetectorService;
use Illuminate\Console\Command;

/**
 * Orquesta el ciclo de monitoreo SEO:
 *   1. Baja datos de GSC (SyncSearchConsoleJob) por proyecto.
 *   2. Detecta quick-wins (QuickWinDetectorService) → seo_opportunities.
 *
 *   php artisan seo:monitor               → todos los proyectos con gsc_enabled
 *   php artisan seo:monitor --project=1   → solo ese proyecto
 */
class RunSeoMonitor extends Command
{
    protected $signature = 'seo:monitor
        {--project= : ID de un proyecto específico}
        {--optimize : Genera metadatos con IA para las top oportunidades (Fase 4)}
        {--optimize-top=5 : Cuántas oportunidades optimizar por proyecto}';

    protected $description = 'Baja datos de Search Console y detecta oportunidades SEO (quick-wins)';

    public function handle(QuickWinDetectorService $detector, OpportunityOptimizerService $optimizer): int
    {
        $query = Project::query()->whereNotNull('gsc_property');

        if ($id = $this->option('project')) {
            $query->whereKey($id);
        } else {
            $query->where('gsc_enabled', true);
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->warn('No hay proyectos con GSC configurado para procesar.');
            return self::SUCCESS;
        }

        foreach ($projects as $project) {
            $this->info("⚙️  {$project->name} ({$project->gsc_property})");

            // 1) Sincronizar datos (inline para ver el resultado al momento).
            SyncSearchConsoleJob::dispatchSync($project);

            // 2) Detectar oportunidades.
            $opportunities = $detector->detectForProject($project);

            if (empty($opportunities)) {
                $this->line('   Sin quick-wins en esta ventana.');
                continue;
            }

            $this->line('   🎯 ' . count($opportunities) . ' oportunidades detectadas. Top 10:');
            $this->table(
                ['Keyword', 'Pos.', 'Impr.', 'CTR', 'Clics pot.', 'Prioridad'],
                array_map(fn ($o) => [
                    mb_strimwidth($o['query'], 0, 45, '…'),
                    round($o['position'], 1),
                    $o['impressions'],
                    round($o['ctr'] * 100, 1) . '%',
                    $o['potential_clicks'],
                    $o['priority_score'],
                ], array_slice($opportunities, 0, 10))
            );

            // Fase 4: generar metadatos con IA para las top oportunidades.
            if ($this->option('optimize')) {
                $top = (int) $this->option('optimize-top');
                $this->line("   🤖 Generando metadatos con IA (top {$top})...");

                $toOptimize = SeoOpportunity::where('project_id', $project->id)
                    ->orderByDesc('priority_score')
                    ->limit($top)
                    ->get();

                foreach ($toOptimize as $opp) {
                    $sug = $optimizer->optimize($opp);
                    if ($sug) {
                        $this->line("   • {$opp->query}");
                        $this->line("     title: {$sug['title']}");
                        $this->line("     meta:  {$sug['meta_description']}");
                    } else {
                        $this->line("   • {$opp->query} — (no se pudo generar; revisa GEMINI_API_KEY)");
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}

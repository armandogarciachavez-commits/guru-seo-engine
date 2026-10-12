<?php

namespace App\Console\Commands;

use App\Jobs\SyncSearchConsoleJob;
use App\Mail\SeoReportMail;
use App\Models\Project;
use App\Models\SeoOpportunity;
use App\Services\OpportunityOptimizerService;
use App\Services\QuickWinDetectorService;
use App\Services\SearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

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
        {--optimize-top=5 : Cuántas oportunidades optimizar por proyecto}
        {--email= : Envía el reporte por email (a esta dirección, o al email del proyecto)}';

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
                        $this->line("   • {$opp->query} — (no se pudo generar; reintentar o revisar OPENROUTER_API_KEY)");
                    }
                }
            }

            // Fase 3: enviar el reporte por email.
            if ($this->option('email') !== null) {
                $to = $this->option('email') ?: $project->email;

                if (empty($to)) {
                    $this->warn('   ✉️  Sin destinatario (usa --email=correo o define el email del proyecto).');
                } else {
                    $window = SearchConsoleService::defaultWindow();
                    $topOpps = SeoOpportunity::where('project_id', $project->id)
                        ->orderByDesc('priority_score')
                        ->limit(20)
                        ->get();

                    Mail::to($to)->send(new SeoReportMail(
                        $project,
                        $topOpps,
                        $window['from'],
                        $window['to']
                    ));

                    $this->info("   ✉️  Reporte enviado a {$to}");
                }
            }
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\SyncSearchConsoleJob;
use App\Mail\SeoReportMail;
use App\Models\GscMetric;
use App\Models\Project;
use App\Models\SeoOpportunity;
use App\Services\OpportunityOptimizerService;
use App\Services\QuickWinDetectorService;
use App\Services\SearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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

        $failed = false;

        foreach ($projects as $project) {
            // Aislar cada proyecto: un fallo (BD, SMTP, IA) no detiene a los demás.
            try {
                $this->processProject($project, $detector, $optimizer);
            } catch (\Throwable $e) {
                Log::error("[SEO Monitor] Proyecto {$project->id}: ".$e->getMessage());
                $this->error("   ❌ Error procesando {$project->name}: ".$e->getMessage());
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function processProject(Project $project, QuickWinDetectorService $detector, OpportunityOptimizerService $optimizer): void
    {
        $this->info("⚙️  {$project->name} ({$project->gsc_property})");

        $runStartedAt = Carbon::now()->startOfSecond();
        $previousCapture = GscMetric::where('project_id', $project->id)->max('captured_at');

        // 1) Sincronizar datos (inline para ver el resultado al momento).
        SyncSearchConsoleJob::dispatchSync($project);

        // Si no entró un lote nuevo (error de GSC o sin datos), no reportar datos viejos.
        $latestCapture = GscMetric::where('project_id', $project->id)->max('captured_at');
        if (! $latestCapture || $latestCapture == $previousCapture) {
            Log::warning("[SEO Monitor] Proyecto {$project->id}: sin lote nuevo de GSC; se omite detección y reporte.");
            $this->warn('   ⚠️  No se obtuvieron datos nuevos de Search Console (revisa el log). Se omite este proyecto.');

            return;
        }

        // 2) Detectar oportunidades.
        $opportunities = $detector->detectForProject($project);

        if (empty($opportunities)) {
            $this->line('   Sin quick-wins en esta ventana.');

            return;
        }

        $this->line('   🎯 '.count($opportunities).' oportunidades detectadas. Top 10:');
        $this->table(
            ['Keyword', 'Pos.', 'Impr.', 'CTR', 'Clics pot.', 'Prioridad'],
            array_map(fn ($o) => [
                mb_strimwidth($o['query'], 0, 45, '…'),
                round($o['position'], 1),
                $o['impressions'],
                round($o['ctr'] * 100, 1).'%',
                $o['potential_clicks'],
                $o['priority_score'],
            ], array_slice($opportunities, 0, 10))
        );

        // Solo oportunidades detectadas en esta corrida y aún abiertas.
        $current = fn () => SeoOpportunity::where('project_id', $project->id)
            ->open()
            ->where('detected_at', '>=', $runStartedAt)
            ->orderByDesc('priority_score');

        // Fase 4: generar metadatos con IA solo para oportunidades nuevas
        // (al optimizarlas pasan a in_progress y no se vuelven a tocar).
        if ($this->option('optimize')) {
            $top = (int) $this->option('optimize-top');
            $this->line("   🤖 Generando metadatos con IA (top {$top})...");

            $toOptimize = $current()->where('status', SeoOpportunity::STATUS_NEW)->limit($top)->get();

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

                return;
            }

            $window = SearchConsoleService::defaultWindow();
            $topOpps = $current()->limit(20)->get();

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

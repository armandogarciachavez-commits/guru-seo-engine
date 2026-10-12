<?php

namespace App\Console\Commands;

use App\Models\GscMetric;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Retención de gsc_metrics: borra lotes con captured_at más antiguo que --days.
 * Siempre conserva el lote más reciente de cada proyecto (lo usa el detector).
 *
 *   php artisan seo:prune-metrics            → 180 días
 *   php artisan seo:prune-metrics --days=90
 */
class PruneGscMetrics extends Command
{
    public const DEFAULT_DAYS = 180;

    protected $signature = 'seo:prune-metrics {--days='.self::DEFAULT_DAYS.' : Días de historial a conservar}';

    protected $description = 'Borra métricas de Search Console más antiguas que la retención (default 180 días)';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days debe ser un entero mayor o igual a 1.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;

        $latestByProject = GscMetric::query()
            ->selectRaw('project_id, MAX(captured_at) as latest')
            ->groupBy('project_id')
            ->pluck('latest', 'project_id');

        foreach ($latestByProject as $projectId => $latest) {
            $keepFrom = $latest && Carbon::parse($latest)->lt($cutoff) ? Carbon::parse($latest) : $cutoff;

            $deleted += GscMetric::where('project_id', $projectId)
                ->where(fn ($q) => $q->where('captured_at', '<', $keepFrom)
                    ->orWhere(fn ($q) => $q->whereNull('captured_at')->where('created_at', '<', $cutoff)))
                ->delete();
        }

        $this->info("🧹 gsc_metrics: {$deleted} filas borradas (anteriores a {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}

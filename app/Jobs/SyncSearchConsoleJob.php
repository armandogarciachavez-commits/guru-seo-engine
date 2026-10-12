<?php

namespace App\Jobs;

use App\Models\GscMetric;
use App\Models\Project;
use App\Services\SearchConsoleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Baja los datos de Search Analytics (grano query+page) de un proyecto
 * y los guarda como un lote nuevo en gsc_metrics.
 */
class SyncSearchConsoleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public Project $project)
    {
    }

    public function handle(): void
    {
        $project = $this->project;

        if (empty($project->gsc_property)) {
            Log::warning("[GSC Sync] Proyecto {$project->id} sin gsc_property; se omite.");
            return;
        }

        try {
            $gsc = new SearchConsoleService();
            $window = SearchConsoleService::defaultWindow();
            $rows = $gsc->fetchSearchAnalytics(
                $project->gsc_property,
                $window['from'],
                $window['to'],
                ['query', 'page'],
                5000
            );
        } catch (\Throwable $e) {
            Log::error("[GSC Sync] Proyecto {$project->id} ({$project->gsc_property}): " . $e->getMessage());
            return;
        }

        if (empty($rows)) {
            Log::info("[GSC Sync] Proyecto {$project->id}: sin datos en la ventana {$window['from']}..{$window['to']}.");
            return;
        }

        $capturedAt = Carbon::now();
        $batch = [];

        foreach ($rows as $r) {
            // keys = [query, page] en ese orden
            $batch[] = [
                'project_id' => $project->id,
                'window_from' => $window['from'],
                'window_to' => $window['to'],
                'query' => $r['keys'][0] ?? null,
                'page' => $r['keys'][1] ?? null,
                'clicks' => $r['clicks'],
                'impressions' => $r['impressions'],
                'ctr' => $r['ctr'],
                'position' => $r['position'],
                'captured_at' => $capturedAt,
                'created_at' => $capturedAt,
                'updated_at' => $capturedAt,
            ];
        }

        // Inserción por bloques para no saturar.
        foreach (array_chunk($batch, 500) as $chunk) {
            GscMetric::insert($chunk);
        }

        Log::info("[GSC Sync] Proyecto {$project->id}: " . count($batch) . " filas guardadas (lote {$capturedAt}).");
    }
}

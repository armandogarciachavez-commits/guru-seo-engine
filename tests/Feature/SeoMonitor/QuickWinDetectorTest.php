<?php

namespace Tests\Feature\SeoMonitor;

use App\Models\SeoOpportunity;
use App\Services\QuickWinDetectorService;

class QuickWinDetectorTest extends SeoMonitorTestCase
{
    public function test_detects_only_striking_distance_keywords_with_enough_impressions(): void
    {
        $project = $this->makeProject();
        $this->makeMetric($project, ['query' => 'ok', 'position' => 8, 'impressions' => 200, 'ctr' => 0.01]);
        $this->makeMetric($project, ['query' => 'top', 'page' => 'https://demo.mx/a', 'position' => 2]);
        $this->makeMetric($project, ['query' => 'lejos', 'page' => 'https://demo.mx/b', 'position' => 30]);
        $this->makeMetric($project, ['query' => 'pocas', 'page' => 'https://demo.mx/c', 'position' => 8, 'impressions' => 4]);

        $opps = (new QuickWinDetectorService)->detectForProject($project);

        $this->assertSame(['ok'], array_column($opps, 'query'));
        // 200 × (0.110 − 0.01) = 20 clics potenciales
        $this->assertSame(20, $opps[0]['potential_clicks']);
    }

    public function test_uses_only_latest_batch_and_orders_by_priority(): void
    {
        $project = $this->makeProject();
        $this->makeMetric($project, ['query' => 'viejo', 'captured_at' => now()->subWeek()]);
        $this->makeMetric($project, ['query' => 'baja', 'page' => 'https://demo.mx/1', 'impressions' => 50]);
        $this->makeMetric($project, ['query' => 'alta', 'page' => 'https://demo.mx/2', 'impressions' => 500]);

        $opps = (new QuickWinDetectorService)->detectForProject($project);

        $this->assertSame(['alta', 'baja'], array_column($opps, 'query'));
    }

    public function test_redetection_keeps_manual_status_and_suggestions(): void
    {
        $project = $this->makeProject();
        $existing = SeoOpportunity::create([
            'project_id' => $project->id, 'query' => 'dentista', 'page' => 'https://demo.mx/',
            'status' => SeoOpportunity::STATUS_APPLIED,
            'ai_suggestions' => ['title' => 'Publicado', 'meta_description' => 'm', 'notes' => ''],
        ]);
        $this->makeMetric($project);

        (new QuickWinDetectorService)->detectForProject($project);

        $existing->refresh();
        $this->assertSame(SeoOpportunity::STATUS_APPLIED, $existing->status);
        $this->assertSame('Publicado', $existing->ai_suggestions['title']);
        $this->assertSame(1, SeoOpportunity::count());
    }

    public function test_expected_ctr_curve_is_monotonic(): void
    {
        $d = new QuickWinDetectorService;
        for ($p = 1; $p < 16; $p++) {
            $this->assertGreaterThan($d->expectedCtr($p + 1), $d->expectedCtr($p));
        }
    }
}

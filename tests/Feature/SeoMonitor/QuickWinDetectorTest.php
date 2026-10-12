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

    public function test_compares_ctr_against_expected_for_current_position(): void
    {
        $project = $this->makeProject();
        // Pos. 10 espera ~2.6 %: 5 % ya rinde bien (antes se marcaba por estar bajo el 11 % de pos. 3).
        $this->makeMetric($project, ['query' => 'rinde-bien', 'page' => 'https://demo.mx/1', 'position' => 10, 'ctr' => 0.05]);
        $this->makeMetric($project, ['query' => 'rinde-mal', 'page' => 'https://demo.mx/2', 'position' => 10, 'ctr' => 0.01]);

        $opps = (new QuickWinDetectorService)->detectForProject($project);

        $this->assertSame(['rinde-mal'], array_column($opps, 'query'));
        // Potencial estimado hacia la posición objetivo: 100 × (0.110 − 0.01) = 10
        $this->assertSame(10, $opps[0]['potential_clicks']);
    }

    public function test_min_impressions_defaults_to_10_and_is_configurable_per_project(): void
    {
        $default = $this->makeProject();
        $this->makeMetric($default, ['query' => 'nueve', 'page' => 'https://demo.mx/9', 'impressions' => 9]);
        $this->makeMetric($default, ['query' => 'diez', 'page' => 'https://demo.mx/10', 'impressions' => 10]);

        $custom = $this->makeProject(['gsc_min_impressions' => 50]);
        $this->makeMetric($custom, ['query' => 'cuarenta', 'page' => 'https://demo.mx/40', 'impressions' => 40]);
        $this->makeMetric($custom, ['query' => 'sesenta', 'page' => 'https://demo.mx/60', 'impressions' => 60]);

        $detector = new QuickWinDetectorService;

        $this->assertSame(10, QuickWinDetectorService::DEFAULT_MIN_IMPRESSIONS);
        $this->assertSame(['diez'], array_column($detector->detectForProject($default), 'query'));
        $this->assertSame(['sesenta'], array_column($detector->detectForProject($custom), 'query'));
    }
}

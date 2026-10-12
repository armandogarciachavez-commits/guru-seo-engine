<?php

namespace Tests\Feature\SeoMonitor;

use App\Models\GscMetric;

class PruneGscMetricsTest extends SeoMonitorTestCase
{
    public function test_deletes_batches_older_than_retention(): void
    {
        $project = $this->makeProject();
        $this->makeMetric($project, ['query' => 'vieja', 'captured_at' => now()->subDays(200)]);
        $this->makeMetric($project, ['query' => 'limite', 'captured_at' => now()->subDays(179)]);
        $this->makeMetric($project, ['query' => 'actual', 'captured_at' => now()]);

        $this->artisan('seo:prune-metrics')->assertSuccessful();

        $this->assertEqualsCanonicalizing(['limite', 'actual'], GscMetric::pluck('query')->all());
    }

    public function test_keeps_latest_batch_of_inactive_project(): void
    {
        $project = $this->makeProject();
        $this->makeMetric($project, ['query' => 'mas-vieja', 'captured_at' => now()->subDays(400)]);
        $this->makeMetric($project, ['query' => 'ultimo-lote', 'captured_at' => now()->subDays(300)]);

        $this->artisan('seo:prune-metrics', ['--days' => 180])->assertSuccessful();

        $this->assertSame(['ultimo-lote'], GscMetric::pluck('query')->all());
    }

    public function test_rejects_invalid_days(): void
    {
        $this->artisan('seo:prune-metrics', ['--days' => 0])->assertFailed();
    }

    public function test_is_scheduled_weekly(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command ?? '', 'seo:prune-metrics'));

        $this->assertCount(1, $events);
    }
}

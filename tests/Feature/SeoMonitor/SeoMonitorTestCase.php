<?php

namespace Tests\Feature\SeoMonitor;

use App\Models\GscMetric;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class SeoMonitorTestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeProject(array $attrs = []): Project
    {
        return Project::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'name' => 'Demo',
            'domain_url' => 'https://demo.mx',
            'target_language' => 'es',
            'brand_voice' => 'profesional',
            'cms_type' => 'wordpress',
            'gsc_property' => 'sc-domain:demo.mx',
            'gsc_enabled' => true,
        ], $attrs));
    }

    protected function makeMetric(Project $project, array $attrs = []): GscMetric
    {
        return GscMetric::create(array_merge([
            'project_id' => $project->id,
            'window_from' => '2026-09-12',
            'window_to' => '2026-10-09',
            'query' => 'dentista',
            'page' => 'https://demo.mx/',
            'clicks' => 0,
            'impressions' => 100,
            'ctr' => 0,
            'position' => 10,
            'captured_at' => now(),
        ], $attrs));
    }
}

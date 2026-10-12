<?php

namespace Tests\Feature\SeoMonitor;

use App\Mail\SeoReportMail;
use App\Models\GscMetric;
use App\Models\SeoOpportunity;
use App\Services\SearchConsoleService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;

class RunSeoMonitorTest extends SeoMonitorTestCase
{
    protected function fakeGsc(array|\Throwable $rows): void
    {
        $gsc = Mockery::mock(SearchConsoleService::class);
        $exp = $gsc->shouldReceive('fetchSearchAnalytics');
        $rows instanceof \Throwable ? $exp->andThrow($rows) : $exp->andReturn($rows);
        $this->app->instance(SearchConsoleService::class, $gsc);
    }

    protected function row(string $query, string $page, int $impr = 300, float $pos = 9, float $ctr = 0.01): array
    {
        return ['keys' => [$query, $page], 'clicks' => (int) round($impr * $ctr), 'impressions' => $impr, 'ctr' => $ctr, 'position' => $pos];
    }

    protected function fakeAi(): void
    {
        config(['services.openrouter.api_key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => [
            'content' => '{"title":"NUEVO","meta_description":"meta nueva","notes":"n"}',
        ]]]])]);
    }

    public function test_weekly_run_does_not_touch_applied_or_dismissed_and_reports_only_current(): void
    {
        Mail::fake();
        $this->fakeAi();
        $project = $this->makeProject();

        $applied = SeoOpportunity::create(['project_id' => $project->id, 'query' => 'aplicada', 'page' => 'https://demo.mx/a',
            'priority_score' => 999, 'status' => 'applied', 'detected_at' => now()->subWeeks(3),
            'ai_suggestions' => ['title' => 'PUBLICADO', 'meta_description' => 'm', 'notes' => '']]);
        $dismissed = SeoOpportunity::create(['project_id' => $project->id, 'query' => 'descartada', 'page' => 'https://demo.mx/d',
            'priority_score' => 998, 'status' => 'dismissed', 'detected_at' => now()->subWeeks(3)]);
        $stale = SeoOpportunity::create(['project_id' => $project->id, 'query' => 'ya-no-califica', 'page' => 'https://demo.mx/s',
            'priority_score' => 997, 'status' => 'new', 'detected_at' => now()->subWeeks(3)]);

        $this->fakeGsc([
            $this->row('aplicada', 'https://demo.mx/a'),
            $this->row('descartada', 'https://demo.mx/d'),
            $this->row('nueva', 'https://demo.mx/n'),
        ]);

        $this->artisan('seo:monitor', ['--optimize' => true, '--email' => 'seo@demo.mx'])->assertSuccessful();

        $this->assertSame('PUBLICADO', $applied->fresh()->ai_suggestions['title']);
        $this->assertNull($dismissed->fresh()->ai_suggestions);
        $this->assertNull($stale->fresh()->ai_suggestions);
        $this->assertSame('NUEVO', SeoOpportunity::where('query', 'nueva')->first()->ai_suggestions['title']);
        Http::assertSentCount(1);

        Mail::assertSent(SeoReportMail::class, function (SeoReportMail $mail) {
            return $mail->opportunities->pluck('query')->all() === ['nueva'];
        });
    }

    public function test_optimize_only_processes_new_and_marks_them_in_progress(): void
    {
        $this->fakeAi();
        $project = $this->makeProject();

        $inProgress = SeoOpportunity::create(['project_id' => $project->id, 'query' => 'trabajando', 'page' => 'https://demo.mx/t',
            'status' => SeoOpportunity::STATUS_IN_PROGRESS, 'detected_at' => now()->subWeek()]);

        $this->fakeGsc([
            $this->row('trabajando', 'https://demo.mx/t'),
            $this->row('nueva', 'https://demo.mx/n'),
        ]);

        $this->artisan('seo:monitor', ['--optimize' => true])->assertSuccessful();

        $this->assertNull($inProgress->fresh()->ai_suggestions);
        $this->assertSame(SeoOpportunity::STATUS_IN_PROGRESS, $inProgress->fresh()->status);

        $nueva = SeoOpportunity::where('query', 'nueva')->first();
        $this->assertSame('NUEVO', $nueva->ai_suggestions['title']);
        $this->assertSame(SeoOpportunity::STATUS_IN_PROGRESS, $nueva->status);
        Http::assertSentCount(1);
    }

    public function test_failed_sync_does_not_report_stale_data(): void
    {
        Mail::fake();
        $project = $this->makeProject();
        $this->makeMetric($project, ['query' => 'dato-viejo', 'captured_at' => now()->subWeeks(5)]);
        $this->fakeGsc(new \RuntimeException('403 sin acceso a la propiedad'));

        $this->artisan('seo:monitor', ['--email' => 'seo@demo.mx'])->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(0, SeoOpportunity::count());
    }

    public function test_long_urls_are_truncated_and_one_project_failure_does_not_stop_others(): void
    {
        Mail::fake();
        $this->makeProject(['name' => 'A']);
        $this->makeProject(['name' => 'B', 'gsc_property' => 'sc-domain:b.mx']);

        $longPage = 'https://demo.mx/?'.str_repeat('utm_source=x&', 60);
        $this->fakeGsc([$this->row('url-larga', $longPage)]);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->artisan('seo:monitor', ['--email' => 'seo@demo.mx'])->assertFailed();

        // Ambos proyectos sincronizaron pese al fallo de SMTP del primero.
        $this->assertSame(2, GscMetric::distinct()->count('project_id'));
        $this->assertSame(500, mb_strlen(GscMetric::first()->page));
    }

    public function test_gsc_pagination_requests_next_page_with_start_row(): void
    {
        $makeRows = fn (int $n) => array_map(function ($i) {
            $r = new \Google\Service\SearchConsole\ApiDataRow;
            $r->setKeys(["q{$i}", 'https://demo.mx/']);
            $r->setClicks(0);
            $r->setImpressions(10);
            $r->setCtr(0);
            $r->setPosition(9);

            return $r;
        }, range(1, $n));

        $resource = Mockery::mock(\Google\Service\SearchConsole\Resource\Searchanalytics::class);
        $startRows = [];
        $resource->shouldReceive('query')->twice()->andReturnUsing(function ($property, $req) use (&$startRows, $makeRows) {
            $startRows[] = $req->getStartRow();
            $resp = new \Google\Service\SearchConsole\SearchAnalyticsQueryResponse;
            $resp->setRows($req->getStartRow() === 0 ? $makeRows(SearchConsoleService::MAX_ROWS_PER_REQUEST) : $makeRows(3));

            return $resp;
        });

        $api = Mockery::mock(\Google\Service\SearchConsole::class);
        $api->searchanalytics = $resource;

        $rows = (new SearchConsoleService($api))->fetchSearchAnalytics('sc-domain:demo.mx', '2026-09-01', '2026-09-28', ['query', 'page'], 50000);

        $this->assertSame([0, SearchConsoleService::MAX_ROWS_PER_REQUEST], $startRows);
        $this->assertCount(SearchConsoleService::MAX_ROWS_PER_REQUEST + 3, $rows);
    }
}

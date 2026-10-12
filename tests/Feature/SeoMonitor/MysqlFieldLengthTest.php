<?php

namespace Tests\Feature\SeoMonitor;

use App\Jobs\SyncSearchConsoleJob;
use App\Models\GscMetric;
use App\Models\SeoOpportunity;
use App\Services\QuickWinDetectorService;
use App\Services\SearchConsoleService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Group;

/**
 * C3 contra MySQL real (modo estricto): sqlite no aplica el largo de VARCHAR,
 * así que estos tests solo corren con DB_CONNECTION=mysql (job tests-mysql del CI).
 */
#[Group('mysql')]
class MysqlFieldLengthTest extends SeoMonitorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requiere MySQL (DB_CONNECTION=mysql).');
        }
    }

    public function test_mysql_rejects_values_longer_than_500(): void
    {
        $project = $this->makeProject();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Data too long/');

        DB::table('gsc_metrics')->insert([
            'project_id' => $project->id, 'window_from' => '2026-09-01', 'window_to' => '2026-09-28',
            'query' => 'q', 'page' => 'https://demo.mx/'.str_repeat('a', 500), 'captured_at' => now(),
        ]);
    }

    public function test_sync_truncates_long_multibyte_query_and_page_and_detector_persists_them(): void
    {
        $project = $this->makeProject();
        $longQuery = str_repeat('dentista en león 🦷 ', 40);           // > 500 chars, multibyte + emoji
        $longPage = 'https://demo.mx/?'.str_repeat('utm_campaña=ñ&', 50); // > 500 chars

        $gsc = Mockery::mock(SearchConsoleService::class);
        $gsc->shouldReceive('fetchSearchAnalytics')->andReturn([
            ['keys' => [$longQuery, $longPage], 'clicks' => 1, 'impressions' => 300, 'ctr' => 0.0033, 'position' => 9],
        ]);
        $this->app->instance(SearchConsoleService::class, $gsc);

        SyncSearchConsoleJob::dispatchSync($project);

        $metric = GscMetric::firstOrFail();
        $this->assertSame(mb_substr($longQuery, 0, 500), $metric->query);
        $this->assertSame(mb_substr($longPage, 0, 500), $metric->page);
        $this->assertSame(500, mb_strlen($metric->query));
        $this->assertSame(500, mb_strlen($metric->page));

        // seo_opportunities también es VARCHAR(500): debe aceptar los valores recortados.
        (new QuickWinDetectorService)->detectForProject($project);
        $this->assertSame($metric->page, SeoOpportunity::firstOrFail()->page);
    }
}

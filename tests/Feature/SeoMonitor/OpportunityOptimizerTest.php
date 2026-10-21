<?php

namespace Tests\Feature\SeoMonitor;

use App\Models\SeoOpportunity;
use App\Services\OpportunityOptimizerService;
use Illuminate\Support\Facades\Http;

class OpportunityOptimizerTest extends SeoMonitorTestCase
{
    protected function makeOpportunity(string $status = SeoOpportunity::STATUS_NEW): SeoOpportunity
    {
        return SeoOpportunity::create([
            'project_id' => $this->makeProject()->id, 'query' => 'dentista', 'page' => 'https://demo.mx/',
            'status' => $status, 'impressions' => 300, 'position' => 9, 'ctr' => 0.01,
        ]);
    }

    protected function fakeAi(?callable $during = null): void
    {
        config(['services.openrouter.api_key' => 'test-key']);
        Http::fake([
            'openrouter.ai/*' => function () use ($during) {
                if ($during) {
                    $during();
                }

                return Http::response(['choices' => [['message' => [
                    'content' => '{"title":"NUEVO","meta_description":"meta","notes":"n"}',
                ]]]]);
            },
            // Fetch del meta actual de la página (PageMetaFetcher).
            '*' => Http::response('<html><head><title>Actual</title><meta name="description" content="Meta actual"></head></html>'),
        ]);
    }

    public function test_new_opportunity_gets_suggestion_and_moves_to_in_progress(): void
    {
        $opp = $this->makeOpportunity();
        $this->fakeAi();

        $sug = app(OpportunityOptimizerService::class)->optimize($opp);

        $this->assertSame('NUEVO', $sug['title']);
        $this->assertSame(SeoOpportunity::STATUS_IN_PROGRESS, $opp->status);
        $this->assertSame(SeoOpportunity::STATUS_IN_PROGRESS, $opp->fresh()->status);
        $this->assertSame('NUEVO', $opp->fresh()->ai_suggestions['title']);
    }

    public function test_status_changed_during_ai_request_is_not_overwritten(): void
    {
        $opp = $this->makeOpportunity();
        $this->fakeAi(fn () => SeoOpportunity::whereKey($opp->id)->update(['status' => SeoOpportunity::STATUS_APPLIED]));

        $sug = app(OpportunityOptimizerService::class)->optimize($opp);

        $this->assertNull($sug);
        $this->assertSame(SeoOpportunity::STATUS_APPLIED, $opp->fresh()->status);
        $this->assertNull($opp->fresh()->ai_suggestions);
    }

    public function test_keeps_current_meta_when_already_optimized(): void
    {
        $opp = $this->makeOpportunity();
        config(['services.openrouter.api_key' => 'test-key']);
        Http::fake([
            'openrouter.ai/*' => Http::response(['choices' => [['message' => [
                'content' => '{"keep_current":true,"reason":"Ya incluye la keyword y un gancho claro"}',
            ]]]]),
            '*' => Http::response('<html><head><title>Título ya bueno</title><meta name="description" content="Meta ya optimizada"></head></html>'),
        ]);

        $sug = app(OpportunityOptimizerService::class)->optimize($opp);

        $this->assertTrue($sug['keep_current']);
        $this->assertSame('Título ya bueno', $sug['title']);
        $this->assertSame('Meta ya optimizada', $sug['meta_description']);
        $this->assertStringContainsString('ya está bien optimizado', $sug['notes']);
        $this->assertTrue($opp->fresh()->ai_suggestions['keep_current']);
    }

    public function test_page_meta_fetcher_parses_and_decodes(): void
    {
        Http::fake(['*' => Http::response(
            '<html><head><title>  Hola Mundo  </title>'
            .'<meta name="description" content="Desc &amp; más info"></head></html>'
        )]);

        $meta = (new \App\Services\PageMetaFetcher)->fetch('https://demo.mx/');

        $this->assertSame('Hola Mundo', $meta['title']);
        $this->assertSame('Desc & más info', $meta['description']);
    }
}

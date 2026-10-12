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
        Http::fake(['openrouter.ai/*' => function () use ($during) {
            if ($during) {
                $during();
            }

            return Http::response(['choices' => [['message' => [
                'content' => '{"title":"NUEVO","meta_description":"meta","notes":"n"}',
            ]]]]);
        }]);
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
}

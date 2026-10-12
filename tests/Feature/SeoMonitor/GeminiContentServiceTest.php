<?php

namespace Tests\Feature\SeoMonitor;

use App\Services\GeminiContentService;
use Illuminate\Support\Facades\Http;

class GeminiContentServiceTest extends SeoMonitorTestCase
{
    public function test_ai_failure_throws_instead_of_returning_empty_article(): void
    {
        config(['services.openrouter.api_key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response(['error' => 'down'], 500)]);

        $this->expectException(\RuntimeException::class);

        app(GeminiContentService::class)->generateArticle($this->makeProject(), 'dentista', []);
    }
}

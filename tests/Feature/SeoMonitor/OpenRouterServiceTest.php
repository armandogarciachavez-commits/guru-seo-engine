<?php

namespace Tests\Feature\SeoMonitor;

use App\Services\OpenRouterService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    protected function fakeAi(string $content, string $finish = 'stop'): void
    {
        config(['services.openrouter.api_key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['finish_reason' => $finish, 'message' => ['content' => $content]]],
        ])]);
    }

    public function test_list_with_single_item_stays_a_list(): void
    {
        $this->fakeAi('[{"title":"Uno","keyword":"kw"}]');

        $plan = (new OpenRouterService)->generateJson('x', ['expect' => 'list']);

        $this->assertSame([['title' => 'Uno', 'keyword' => 'kw']], $plan);
    }

    public function test_list_wrapped_in_object_is_unwrapped(): void
    {
        $this->fakeAi("```json\n{\"articles\":[{\"title\":\"A\"},{\"title\":\"B\"}]}\n```");

        $plan = (new OpenRouterService)->generateJson('x', ['expect' => 'list']);

        $this->assertSame([['title' => 'A'], ['title' => 'B']], $plan);
    }

    public function test_wrapper_with_several_arrays_picks_the_items_list(): void
    {
        $this->fakeAi('{"warnings":[],"articles":[{"title":"Dental care","keyword":"dentist"}]}');

        $plan = (new OpenRouterService)->generateJson('x', ['expect' => 'list']);

        $this->assertSame([['title' => 'Dental care', 'keyword' => 'dentist']], $plan);
    }

    public function test_wrapper_with_tags_before_items_picks_the_items_list(): void
    {
        $this->fakeAi('Plan: {"tags":["a","b"],"items":[{"title":"Uno"},{"title":"Dos"}]}');

        $plan = (new OpenRouterService)->generateJson('x', ['expect' => 'list']);

        $this->assertSame([['title' => 'Uno'], ['title' => 'Dos']], $plan);
    }

    public function test_loose_object_becomes_single_item_list(): void
    {
        $this->fakeAi('Aquí está: {"title":"Solo","keyword":"k"}');

        $plan = (new OpenRouterService)->generateJson('x', ['expect' => 'list']);

        $this->assertSame([['title' => 'Solo', 'keyword' => 'k']], $plan);
    }

    public function test_object_with_nested_array_is_returned_as_object(): void
    {
        $this->fakeAi('{"title":"T","meta_description":"M","tags":["a","b"]}');

        $data = (new OpenRouterService)->generateJson('x');

        $this->assertSame('T', $data['title']);
        $this->assertSame(['a', 'b'], $data['tags']);
    }

    public function test_malformed_json_returns_null(): void
    {
        $this->fakeAi('{"title": "sin cerrar"');

        $this->assertNull((new OpenRouterService)->generateJson('x'));
    }

    public function test_http_error_and_missing_key_return_null(): void
    {
        config(['services.openrouter.api_key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response(['error' => 'down'], 500)]);
        $this->assertNull((new OpenRouterService)->generate('x'));

        config(['services.openrouter.api_key' => null]);
        $this->assertNull((new OpenRouterService)->generate('x'));
    }

    public function test_retries_on_connection_timeout_then_succeeds(): void
    {
        config(['services.openrouter.api_key' => 'test-key']);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response([
                'choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'Listo']]],
            ]);
        });

        $result = (new OpenRouterService)->generate('x');

        $this->assertSame('Listo', $result);
        $this->assertSame(3, $attempts, 'Debió reintentar hasta tener respuesta.');
    }

    public function test_persistent_timeout_returns_null_after_retries(): void
    {
        config(['services.openrouter.api_key' => 'test-key']);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out');
        });

        $result = (new OpenRouterService)->generate('x');

        $this->assertNull($result);
        $this->assertSame(3, $attempts, 'Debió agotar los 3 intentos y rendirse con null.');
    }
}

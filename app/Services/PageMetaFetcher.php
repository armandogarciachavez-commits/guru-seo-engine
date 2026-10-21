<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lee el <title> y el <meta name="description"> actuales de una URL pública.
 *
 * Se usa para darle contexto al optimizador: así la IA solo sugiere cambios si
 * realmente mejoran el meta existente (y no propone un downgrade cuando la
 * página ya está bien optimizada).
 */
class PageMetaFetcher
{
    /**
     * @return array{title:?string,description:?string}
     */
    public function fetch(string $url): array
    {
        $empty = ['title' => null, 'description' => null];

        if (! preg_match('#^https?://#i', $url)) {
            return $empty;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'GuruSEO-MetaFetcher/1.0'])
                ->connectTimeout(10)
                ->timeout(15)
                ->retry(2, 1000, throw: false)
                ->get($url);

            if ($response->failed()) {
                return $empty;
            }

            $html = $response->body();

            return [
                'title' => $this->extractTitle($html),
                'description' => $this->extractDescription($html),
            ];
        } catch (\Throwable $e) {
            Log::warning('[PageMeta] No se pudo leer el meta de '.$url.': '.$e->getMessage());

            return $empty;
        }
    }

    protected function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return $this->clean($m[1]);
        }

        return null;
    }

    protected function extractDescription(string $html): ?string
    {
        // content después de name="description"
        if (preg_match('/<meta\s+[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/is', $html, $m)) {
            return $this->clean($m[1]);
        }

        // content antes de name="description"
        if (preg_match('/<meta\s+[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/is', $html, $m)) {
            return $this->clean($m[1]);
        }

        return null;
    }

    protected function clean(string $value): ?string
    {
        $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $value !== '' ? $value : null;
    }
}

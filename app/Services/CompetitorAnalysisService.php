<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Str;
use DOMDocument;
use DOMXPath;

class CompetitorAnalysisService
{
    public function analyze(string $keyword, string $targetLanguage): array
    {
        // 1. Construct Google Search URL
        // target_language e.g. 'es-MX' -> hl=es, gl=mx
        $langParts = explode('-', $targetLanguage);
        $hl = $langParts[0] ?? 'en';
        $gl = $langParts[1] ?? 'US';

        $url = "https://www.google.com/search?q=" . urlencode($keyword) . "&hl={$hl}&gl={$gl}";

        // 2. Get Search Results (Top 3) via Puppeteer
        // Using evaluate to run JS in the browser context to extract links is more robust than parsing HTML manually
        try {
            $links = Browsershot::url($url)
                ->setNodeBinary('/usr/local/bin/node')
                ->setNpmBinary('/usr/local/bin/npm')
                ->noSandbox()
                ->timeout(30000)
                ->userAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                ->evaluate("JSON.stringify(Array.from(document.querySelectorAll('div.g a h3')).slice(0, 3).map(el => el.closest('a').href))");

            $links = json_decode($links, true);
        } catch (\Exception $e) {
            $links = [];
        }

        if (!is_array($links)) {
            $links = [];
        }

        $results = [];

        foreach ($links as $link) {
            // Skip non-analyzable links (pdf, etc if needed)
            if (empty($link))
                continue;

            $data = $this->analyzeUrl($link);
            if ($data) {
                $results[] = $data;
            }
        }

        return $results;
    }

    protected function analyzeUrl(string $url): ?array
    {
        try {
            // Get content of the competitor page
            $html = Browsershot::url($url)
                ->setNodeBinary('/usr/local/bin/node')
                ->setNpmBinary('/usr/local/bin/npm')
                ->noSandbox()
                ->timeout(30000)
                ->userAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
                ->bodyHtml();

            // Extract details
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new DOMXPath($dom);

            // Headings
            $headings = [];
            foreach (['h1', 'h2', 'h3'] as $tag) {
                $nodes = $dom->getElementsByTagName($tag);
                foreach ($nodes as $node) {
                    $headings[$tag][] = trim($node->textContent);
                }
            }

            // Word Count (Approximate text content)
            $text = $dom->textContent;
            // Remove scripts/styles
            foreach ($xpath->query('//script|//style') as $node) {
                $node->parentNode->removeChild($node);
            }
            $cleanText = strip_tags($dom->textContent);
            $wordCount = str_word_count($cleanText);

            return [
                'url' => $url,
                'headings' => $headings,
                'word_count' => $wordCount,
            ];

        } catch (\Exception $e) {
            // Log error or ignore
            return null;
        }
    }
}

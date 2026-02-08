<?php

namespace App\Services;

use Spatie\Crawler\CrawlObservers\CrawlObserver;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use App\Models\CrawlResult;
use App\Models\Project;
use DOMDocument;

class SeoCrawler extends CrawlObserver
{
    protected $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Limpieza NUCLEAR de texto
     */
    private function cleanText(?string $text): ?string
    {
        if ($text === null) return null;

        // 1. Convertir a UTF-8 ignorando caracteres ilegales
        // 'IGNORE' descarta lo que no puede leer
        $text = iconv(mb_detect_encoding($text, mb_detect_order(), true) ?: 'UTF-8', 'UTF-8//IGNORE', $text);

        // 2. Eliminar caracteres de control invisibles (excepto saltos de línea)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        return trim($text);
    }

    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null 
    ): void {
        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        $html = (string) $response->getBody();
        
        // Limpiar HTML antes de cargarlo
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        
        libxml_use_internal_errors(true); 
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        libxml_clear_errors();

        $h1 = $dom->getElementsByTagName('h1')->item(0)?->nodeValue;

        $metaDescription = '';
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $metaDescription = $meta->getAttribute('content');
            }
        }

        $title = $dom->getElementsByTagName('title')->item(0)?->nodeValue;

        CrawlResult::create([
            'project_id' => $this->project->id,
            'url' => (string) $url,
            'status_code' => $response->getStatusCode(),
            'title' => $this->cleanText($title), 
            'h1' => $this->cleanText($h1),       
            'meta_description' => $this->cleanText($metaDescription), 
            'word_count' => str_word_count(strip_tags($html)),
        ]);
    }

    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null 
    ): void {
        $errorMsg = $this->cleanText($requestException->getMessage());

        CrawlResult::create([
            'project_id' => $this->project->id,
            'url' => (string) $url,
            'status_code' => $requestException->getCode() ?: 500, 
            'title' => 'Error de Rastreo',
            'h1' => 'Error: ' . substr($errorMsg, 0, 200),
        ]);
    }
}
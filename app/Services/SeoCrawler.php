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
     * Limpia el texto y lo recorta si es necesario para que no explote la BD.
     */
    private function cleanText(?string $text, int $limit = 250): ?string
    {
        if ($text === null) return null;

        // 1. Limpieza de codificación (UTF-8)
        $text = iconv(mb_detect_encoding($text, mb_detect_order(), true) ?: 'UTF-8', 'UTF-8//IGNORE', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = trim($text);

        // 2. RECORTAR CON AVISO (Para proteger tu App)
        // Si el texto es más largo que lo que soporta la BD (255 chars), lo cortamos.
        if (mb_strlen($text) > $limit) {
            // Cortamos y agregamos una marca visual para que sepas que hay un problema SEO
            return mb_substr($text, 0, $limit - 15) . '... [EXCESO]';
        }

        return $text;
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

        // AQUÍ ESTÁ LA PROTECCIÓN
        // Usamos cleanText con límite de 250 caracteres.
        // Si el sitio tiene un título de 500 letras, se guardarán las primeras 235 + "... [EXCESO]"
        // Así tu app no falla, y tú sabes que tienes que arreglar ese sitio.
        
        CrawlResult::create([
            'project_id' => $this->project->id,
            'url' => (string) $url,
            'status_code' => $response->getStatusCode(),
            'title' => $this->cleanText($title, 250), 
            'h1' => $this->cleanText($h1, 250),       
            'meta_description' => $this->cleanText($metaDescription, 500), 
            'word_count' => str_word_count(strip_tags($html)),
        ]);
    }

    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null 
    ): void {
        $errorMsg = $this->cleanText($requestException->getMessage(), 200);

        CrawlResult::create([
            'project_id' => $this->project->id,
            'url' => (string) $url,
            'status_code' => $requestException->getCode() ?: 500, 
            'title' => 'Error de Rastreo',
            'h1' => 'Error: ' . $errorMsg,
        ]);
    }
}
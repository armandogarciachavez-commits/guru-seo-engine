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
     * Se ejecuta cuando encuentra una página exitosa (Código 200)
     */
    public function crawled(
        UriInterface $url,
        ResponseInterface $response,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null // <--- ¡ESTO FALTABA! (Nuevo parámetro de Spatie)
    ): void {
        // Solo analizamos HTML (ignoramos imágenes, CSS, JS)
        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return;
        }

        $html = (string) $response->getBody();
        
        // Analizar el HTML (Scraping básico)
        $dom = new DOMDocument();
        @$dom->loadHTML($html); // El @ silencia advertencias de HTML mal formado

        // Extraer H1
        $h1 = $dom->getElementsByTagName('h1')->item(0)?->nodeValue;

        // Extraer Meta Description
        $metaDescription = '';
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $metaDescription = $meta->getAttribute('content');
            }
        }

        // Extraer Título
        $title = $dom->getElementsByTagName('title')->item(0)?->nodeValue;

        // Guardar en la Base de Datos
        CrawlResult::create([
            'project_id' => $this->project->id,
            'url' => (string) $url,
            'status_code' => $response->getStatusCode(),
            'title' => trim($title),
            'h1' => trim($h1),
            'meta_description' => trim($metaDescription),
            'word_count' => str_word_count(strip_tags($html)),
        ]);
    }

    /**
     * Se ejecuta cuando falla una página (Error 404, 500, DNS, etc.)
     */
    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null // <--- ¡ESTO TAMBIÉN FALTABA!
    ): void {
        CrawlResult::create([
            'project_id' => $this->project->id,
            'url' => (string) $url,
            'status_code' => $requestException->getCode() ?: 500, 
            'title' => 'Error de Rastreo',
            'h1' => 'Error: ' . $requestException->getMessage(),
        ]);
    }
}
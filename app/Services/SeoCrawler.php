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
     * Función mágica para limpiar caracteres extraños (UTF-8 Fixer)
     */
    private function cleanText(?string $text): ?string
    {
        if ($text === null) return null;

        // 1. Detectar si no es UTF-8 y convertirlo
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // 2. Eliminar caracteres de control invisibles que rompen JSON
        // (Mantiene saltos de línea y tabulaciones, quita el resto)
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
        
        // --- INICIO DE LA LIMPIEZA ---
        // Silenciar errores de HTML mal formado
        libxml_use_internal_errors(true); 
        $dom = new DOMDocument();
        
        // Truco: Forzar UTF-8 al cargar el HTML
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML($html);
        libxml_clear_errors();
        // --- FIN DE LA LIMPIEZA ---

        // Extraer H1
        $h1Node = $dom->getElementsByTagName('h1')->item(0);
        $h1 = $h1Node ? $h1Node->nodeValue : null;

        // Extraer Meta Description
        $metaDescription = '';
        $metas = $dom->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $metaDescription = $meta->getAttribute('content');
            }
        }

        // Extraer Título
        $titleNode = $dom->getElementsByTagName('title')->item(0);
        $title = $titleNode ? $titleNode->nodeValue : null;

        // Guardar usando el limpiador
        CrawlResult::create([
            'project_id' => $this->project->id,
            'url' => (string) $url,
            'status_code' => $response->getStatusCode(),
            'title' => $this->cleanText($title), // <--- Limpiando
            'h1' => $this->cleanText($h1),       // <--- Limpiando
            'meta_description' => $this->cleanText($metaDescription), // <--- Limpiando
            'word_count' => str_word_count(strip_tags($html)),
        ]);
    }

    public function crawlFailed(
        UriInterface $url,
        RequestException $requestException,
        ?UriInterface $foundOnUrl = null,
        ?string $linkText = null 
    ): void {
        // También limpiamos el mensaje de error por si acaso trae basura binaria
        $errorMsg = $this->cleanText($requestException->getMessage());

        CrawlResult::create([
            'project_id' => $this->project->id,
            'url' => (string) $url,
            'status_code' => $requestException->getCode() ?: 500, 
            'title' => 'Error de Rastreo',
            'h1' => 'Error: ' . substr($errorMsg, 0, 200), // Cortamos para no saturar
        ]);
    }
}
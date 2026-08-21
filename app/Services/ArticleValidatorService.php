<?php

namespace App\Services;

class ArticleValidatorService
{
    /**
     * Frases de relleno prohibidas por el prompt maestro.
     */
    private const FORBIDDEN_PHRASES = [
        'hoy en día',
        'en el mundo actual',
        'es importante mencionar',
        'sin duda alguna',
    ];

    private const MIN_WORDS = 850;

    private const MAX_WORDS = 1100;

    /**
     * Valida que el HTML generado por la IA cumpla las reglas del prompt.
     *
     * @return array{critical: string[], warnings: string[]}
     */
    public function validate(?string $html, ?string $keyword = null, ?string $domainUrl = null): array
    {
        $critical = [];
        $warnings = [];

        if ($html === null || trim(strip_tags($html)) === '') {
            return [
                'critical' => ['El artículo no tiene contenido.'],
                'warnings' => [],
            ];
        }

        $text = trim(strip_tags($html));
        $plainLower = mb_strtolower($text);

        // 1. Salida HTML pura (sin Markdown ni backticks)
        if (str_contains($html, '```')) {
            $critical[] = 'Contiene bloques de código Markdown (```).';
        }
        if (preg_match('/^#{1,6}\s/m', $html) || preg_match('/\*\*[^*]+\*\*/', $html)) {
            $warnings[] = 'Contiene sintaxis Markdown (encabezados # o negritas **).';
        }

        // 2. Longitud
        $wordCount = str_word_count($text, 0, 'áéíóúüñÁÉÍÓÚÜÑ');
        if ($wordCount < 300) {
            $critical[] = "Contenido demasiado corto ({$wordCount} palabras).";
        } elseif ($wordCount < self::MIN_WORDS || $wordCount > self::MAX_WORDS) {
            $warnings[] = "Longitud fuera de rango ({$wordCount} palabras; esperado ".self::MIN_WORDS.'-'.self::MAX_WORDS.').';
        }

        // 3. Keyword presente
        if ($keyword !== null && trim($keyword) !== '' && ! str_contains($plainLower, mb_strtolower(trim($keyword)))) {
            $warnings[] = "La keyword '{$keyword}' no aparece en el contenido.";
        }

        // 4. Estructura
        $h2Count = preg_match_all('/<h2[\s>]/i', $html);
        if ($h2Count < 2) {
            $warnings[] = "Estructura pobre: solo {$h2Count} secciones <h2> (esperado 4-6).";
        }
        if (! preg_match('/<ul[\s>]/i', $html)) {
            $warnings[] = 'No incluye ninguna lista <ul>.';
        }

        // 5. Frases de relleno prohibidas
        foreach (self::FORBIDDEN_PHRASES as $phrase) {
            if (str_contains($plainLower, $phrase)) {
                $warnings[] = "Usa la frase prohibida: '{$phrase}'.";
            }
        }

        // 6. Datos de contacto sin definir filtrados al contenido
        if (str_contains($text, 'No especificado')) {
            $critical[] = "Incluye el texto 'No especificado' (datos de contacto vacíos).";
        }

        // 7. Enlaces: solo al dominio del proyecto
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)["\']/i', $html, $matches)) {
            $allowedHost = $domainUrl ? parse_url($this->normalizeUrl($domainUrl), PHP_URL_HOST) : null;

            foreach ($matches[1] as $href) {
                if (str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                    continue;
                }

                $host = parse_url($this->normalizeUrl($href), PHP_URL_HOST);

                if ($host !== null && $allowedHost !== null && ! $this->sameDomain($host, $allowedHost)) {
                    $critical[] = "Enlace a dominio externo no permitido: {$href}";
                }
            }

            if (count($matches[1]) > 4) {
                $warnings[] = 'Demasiados enlaces ('.count($matches[1]).'; máximo recomendado 2 internos).';
            }
        }

        return ['critical' => $critical, 'warnings' => $warnings];
    }

    /**
     * Valida y devuelve el estado sugerido para el artículo.
     */
    public function hasCriticalIssues(array $result): bool
    {
        return ! empty($result['critical']);
    }

    private function normalizeUrl(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : 'https://'.ltrim($url, '/');
    }

    private function sameDomain(string $host, string $allowedHost): bool
    {
        $host = strtolower(preg_replace('/^www\./', '', $host));
        $allowedHost = strtolower(preg_replace('/^www\./', '', $allowedHost));

        return $host === $allowedHost;
    }
}

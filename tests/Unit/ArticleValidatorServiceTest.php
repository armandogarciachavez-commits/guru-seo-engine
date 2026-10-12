<?php

namespace Tests\Unit;

use App\Services\ArticleValidatorService;
use PHPUnit\Framework\TestCase;

class ArticleValidatorServiceTest extends TestCase
{
    private ArticleValidatorService $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ArticleValidatorService;
    }

    private function makeValidHtml(string $keyword = 'plomería'): string
    {
        $paragraph = '<p>'.str_repeat("Servicio profesional de {$keyword} con garantía y atención inmediata en toda la ciudad. ", 60).'</p>';

        return "<h1>Guía de {$keyword}</h1>"
            .'<h2>Sección uno</h2>'.$paragraph
            .'<h2>Sección dos</h2>'.$paragraph
            .'<ul><li>Beneficio uno</li><li>Beneficio dos</li></ul>';
    }

    public function test_empty_content_is_critical(): void
    {
        $result = $this->validator->validate(null);

        $this->assertTrue($this->validator->hasCriticalIssues($result));
        $this->assertNotEmpty($result['critical']);
    }

    public function test_html_only_whitespace_is_critical(): void
    {
        $result = $this->validator->validate('<p>   </p>');

        $this->assertTrue($this->validator->hasCriticalIssues($result));
    }

    public function test_valid_article_has_no_critical_issues(): void
    {
        $result = $this->validator->validate($this->makeValidHtml(), 'plomería', 'https://example.com');

        $this->assertFalse($this->validator->hasCriticalIssues($result));
    }

    public function test_markdown_code_block_is_critical(): void
    {
        $html = '```html'.$this->makeValidHtml().'```';
        $result = $this->validator->validate($html, 'plomería', 'https://example.com');

        $this->assertTrue($this->validator->hasCriticalIssues($result));
    }

    public function test_short_content_is_critical(): void
    {
        $result = $this->validator->validate('<h2>Hola</h2><p>Texto muy corto.</p>');

        $this->assertTrue($this->validator->hasCriticalIssues($result));
    }

    public function test_missing_keyword_is_warning(): void
    {
        $result = $this->validator->validate($this->makeValidHtml('plomería'), 'electricidad', 'https://example.com');

        $this->assertFalse($this->validator->hasCriticalIssues($result));
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_no_especificado_is_critical(): void
    {
        $html = $this->makeValidHtml().'<p>Teléfono: No especificado</p>';
        $result = $this->validator->validate($html, 'plomería', 'https://example.com');

        $this->assertTrue($this->validator->hasCriticalIssues($result));
    }

    public function test_external_link_is_critical(): void
    {
        $html = $this->makeValidHtml().'<a href="https://otro-sitio.com/pagina">Enlace externo</a>';
        $result = $this->validator->validate($html, 'plomería', 'https://example.com');

        $this->assertTrue($this->validator->hasCriticalIssues($result));
    }

    public function test_internal_link_is_allowed(): void
    {
        $html = $this->makeValidHtml().'<a href="https://www.example.com/servicios">Servicios</a>';
        $result = $this->validator->validate($html, 'plomería', 'https://example.com');

        $this->assertFalse($this->validator->hasCriticalIssues($result));
    }

    public function test_forbidden_phrase_is_warning(): void
    {
        $html = $this->makeValidHtml().'<p>Hoy en día todos necesitan este servicio.</p>';
        $result = $this->validator->validate($html, 'plomería', 'https://example.com');

        $this->assertFalse($this->validator->hasCriticalIssues($result));
        $this->assertNotEmpty($result['warnings']);
    }
}

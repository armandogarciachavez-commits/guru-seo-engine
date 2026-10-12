<?php

namespace App\Services;

use App\Models\Project;

class GeminiContentService
{
    /**
     * Generate an article based on project settings and competitor data.
     */
    public function generateArticle(Project $project, string $keyword, array $competitorData): string
    {
        $prompt = $this->buildPrompt($project, $keyword, $competitorData);

        $content = (new OpenRouterService)->generate($prompt, [
            'system' => 'Eres un redactor SEO experto. Devuelves HTML limpio.',
            'temperature' => 0.7,
        ]);

        if (empty($content)) {
            throw new \RuntimeException('La IA no devolvió contenido. Reintenta o revisa OPENROUTER_API_KEY.');
        }

        return $content;
    }

    /**
     * Construct the prompt for Gemini.
     */
    private function buildPrompt(Project $project, string $keyword, array $competitorData): string
    {
        $competitorSummary = json_encode($competitorData);

        return <<<PROMPT
You are an expert SEO content writer.

Project Details:
- Target City: {$project->target_city}
- Target Location (SEO Region): {$project->target_location}
- Target Language: {$project->target_language}
- Brand Voice: {$project->brand_voice}
- Business Context: {$project->business_context}
- Target Audience: {$project->target_audience}
- Key Services: {$project->key_services}
- CTA Instruction: {$project->cta_instruction}

Task:
Write a comprehensive, SEO-optimized article strictly in HTML format (using <h1>, <h2>, <p>, <ul>, etc.) for the keyword: "{$keyword}".

Competitor Insights:
{$competitorSummary}

Requirements:
1. Use the competitor data to ensure our content is more comprehensive.
2. Adopt the specified brand voice.
3. If a target city is provided, localize the content accordingly.
4. Do NOT include <html>, <head>, or <body> tags. Just the content.
PROMPT;
    }
}

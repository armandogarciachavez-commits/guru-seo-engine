<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\CompetitorAnalysisService;
use App\Services\GeminiContentService;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    protected $competitorAnalysis;
    protected $geminiService;

    public function __construct(CompetitorAnalysisService $competitorAnalysis, GeminiContentService $geminiService)
    {
        $this->competitorAnalysis = $competitorAnalysis;
        $this->geminiService = $geminiService;
    }

    public function store(Request $request, Project $project)
    {
        $request->validate([
            'keyword' => 'required|string|max:255',
        ]);

        try {
            // 1. Competitor Analysis
            $competitorData = $this->competitorAnalysis->analyze($request->keyword, $project->target_language);

            // 2. Generate Content
            $content = $this->geminiService->generateArticle($project, $request->keyword, $competitorData);

            if (!$content) {
                return back()->with('error', 'Failed to generate content. Please check API keys or try again.');
            }

            // 3. Save Article
            $project->articles()->create([
                'keyword' => $request->keyword,
                'slug' => \Illuminate\Support\Str::slug($request->keyword . '-' . time()), // Unique slug
                'title' => ucwords($request->keyword), // Basic title for now
                'status' => 'completed',
                'html_content' => $content,
                'competitor_data' => $competitorData // Correct column name
            ]);

            return back()->with('success', 'Article generated successfully!');

        } catch (\Exception $e) {
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
}

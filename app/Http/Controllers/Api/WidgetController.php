<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class WidgetController extends Controller
{
    /**
     * Get the latest published articles for a project.
     *
     * @param int|string $projectId
     * @return JsonResponse
     */
    public function index($projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        $articles = $project->articles()
            ->where('status', 'published')
            ->latest()
            ->take(3)
            ->get(['id', 'title', 'keyword', 'created_at', 'updated_at']); // Select only necessary fields

        return response()
            ->json($articles)
            ->header('Access-Control-Allow-Origin', '*');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function getArticles($uuid)
    {
        // Validación básica
        if (!$uuid) return response()->json(['error' => 'Falta UUID'], 400);

        $project = Project::where('uuid', $uuid)->first();
        if (!$project) return response()->json(['error' => 'Proyecto no encontrado'], 404);

        $articles = $project->articles()
            ->whereIn('status', ['generated', 'published']) 
            ->orderBy('scheduled_date', 'desc')
            ->take(10)
            ->get(['title', 'content', 'scheduled_date', 'keyword', 'status']);

        return response()->json([
            'site' => $project->name,
            'articles' => $articles
        ]);
    }
}
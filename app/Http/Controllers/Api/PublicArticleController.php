<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;
use App\Models\Project;

class PublicArticleController extends Controller
{
    public function index(Request $request)
    {
        // 1. Recibimos la 'api_key' que manda el plugin de WordPress
        $uuid = $request->query('api_key');

        if (!$uuid) {
            return response()->json([
                'error' => 'Falta la API Key (UUID). Configúrala en el plugin.'
            ], 400);
        }

        // 2. Buscamos el proyecto usando la columna 'uuid'
        // (Confirmado por tu migración 2026_02_09_183309_add_uuid...)
        $project = Project::where('uuid', $uuid)->first();

        if (!$project) {
            return response()->json([
                'error' => 'API Key inválida. Proyecto no encontrado.'
            ], 403);
        }

        // 3. Obtenemos los artículos de ESE proyecto
        $articles = Article::where('project_id', $project->id)
                           ->where('status', 'published') // Solo los publicados
                           ->orderBy('created_at', 'desc') // Los más nuevos primero
                           ->limit(20) // Límite de seguridad para no saturar
                           ->get();

        return response()->json($articles);
    }
}
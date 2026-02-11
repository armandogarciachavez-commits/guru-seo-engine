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
        // 1. Recibimos la 'api_key' (UUID)
        $uuid = $request->query('api_key');

        if (!$uuid) {
            return response()->json([
                'error' => 'Falta la API Key (UUID). Configúrala en el plugin.'
            ], 400);
        }

        // 2. Buscamos el proyecto
        $project = Project::where('uuid', $uuid)->first();

        if (!$project) {
            return response()->json([
                'error' => 'API Key inválida. Proyecto no encontrado.'
            ], 403);
        }

        // 3. --- LÓGICA DE CANTIDAD (NUEVO) ---
        // Leemos el límite que pide el plugin. Si no pide, damos 9.
        $limit = $request->query('limit', 9);
        
        // Convertimos a número entero
        $limit = (int) $limit;

        // Validaciones de seguridad (Mínimo 1, Máximo 50)
        if ($limit < 1) $limit = 1;
        if ($limit > 50) $limit = 50;

        // 4. Obtenemos los artículos
        $articles = Article::where('project_id', $project->id)
                           ->where('status', 'published') // Solo publicados
                           ->orderBy('created_at', 'desc') // Los más nuevos primero
                           ->limit($limit) // ✅ APLICAMOS EL LÍMITE DINÁMICO
                           ->get();

        return response()->json($articles);
    }
}
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ArticleController;
use App\Models\Article;

// 1. Redirección inicial (Cuando entras a la raíz, te lleva a Proyectos)
Route::get('/', function () {
    return redirect()->route('projects.index');
});

// 2. Rutas del Panel de Administración (Lo que ya tenías)
Route::resource('projects', ProjectController::class)->only(['index', 'store', 'show']);
Route::post('projects/{project}/articles', [ArticleController::class, 'store'])->name('projects.articles.store');

// 3. NUEVA RUTA PÚBLICA (Para leer las noticias completas)
Route::get('/articles/{slug}', function ($slug) {
    // Busca el artículo por su URL amigable (slug)
    $article = Article::where('slug', $slug)->firstOrFail();

    // Muestra la vista de lectura
    return view('article', ['article' => $article]);
})->name('articles.show');
use Illuminate\Support\Facades\Http;

// RUTA MÁGICA: Generar artículo con IA
Route::get('/generar-ia', function () {

    // 1. Datos del Cliente (Framboyán)
    $project = \App\Models\Project::first();
    $keyword = "mejores mariscos en manzanillo";

    // 2. El Prompt para Gemini
    $prompt = "Actúa como un experto en SEO local. Escribe un artículo de blog corto (300 palabras) en formato HTML " .
        "para el negocio '{$project->name}'. El tema es: '{$keyword}'. " .
        "Usa un tono: {$project->brand_voice}. " .
        "Incluye etiquetas <h2> y <p>. No uses Markdown, solo HTML puro.";

    // 3. Llamada a la API de Google Gemini
    $apiKey = env('GEMINI_API_KEY');
    $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ]
    ]);

    // 4. Procesar la respuesta
    if ($response->successful()) {
        $aiText = $response->json()['candidates'][0]['content']['parts'][0]['text'];

        // Guardar en la Base de Datos
        $article = \App\Models\Article::create([
            'project_id' => $project->id,
            'keyword' => $keyword,
            'title' => 'Los Mejores Mariscos de Manzanillo (IA Generado)',
            'slug' => 'mariscos-ia-' . time(),
            'html_content' => $aiText,
            'status' => 'published'
        ]);

        return "<h1>¡Éxito! 🎉</h1><p>La IA escribió el artículo.</p><a href='/articles/{$article->slug}'>Ver Artículo Generado</a>";
    } else {
        return "Error: " . $response->body();
    }
});



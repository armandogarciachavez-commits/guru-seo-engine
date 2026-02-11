<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Article; // ✅ IMPORTANTE: Importamos el modelo Article
use App\Http\Controllers\Api\WidgetContentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Ruta existente (la dejamos por si la usas en el futuro con UUID)
Route::get('/v1/widget/{uuid}', [WidgetContentController::class, 'getArticles']);

// --- ✅ NUEVA RUTA PARA QUE FUNCIONE EL PLUGIN ACTUAL ---
Route::get('/articles', function () {
    // Busca artículos PUBLICADOS (status='published'), los ordena por fecha y devuelve los últimos 20.
    return Article::where('status', 'published')
        ->orderBy('scheduled_date', 'desc')
        ->limit(20) // Límite de seguridad
        ->get();
});
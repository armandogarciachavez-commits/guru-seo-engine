<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Article;
use App\Http\Controllers\Api\WidgetContentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Ruta del Widget (La mantenemos por si acaso)
Route::get('/v1/widget/{uuid}', [WidgetContentController::class, 'getArticles']);

// --- ✅ RUTA NUEVA PARA TU WORDPRESS ---
Route::get('/articles', function () {
    // Devuelve los artículos publicados
    return Article::where('status', 'published')
        ->orderBy('scheduled_date', 'desc')
        ->limit(20)
        ->get();
});
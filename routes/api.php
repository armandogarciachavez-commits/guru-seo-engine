<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WidgetContentController;
// Importamos el controlador que creamos en el Paso 1
use App\Http\Controllers\Api\PublicArticleController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Ruta de usuario (Autenticación)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Ruta del Widget (Existente)
Route::get('/v1/widget/{uuid}', [WidgetContentController::class, 'getArticles']);

// ✅ RUTA DEL PLUGIN WORDPRESS
// Esta línea conecta la petición con tu nuevo controlador
Route::get('/articles', [PublicArticleController::class, 'index']);
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ContentController; // <--- CRÍTICO: Importar el controlador

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// --- LA RUTA QUE FALTABA ---
Route::get('/v1/widget/{uuid}', [ContentController::class, 'getArticles']);
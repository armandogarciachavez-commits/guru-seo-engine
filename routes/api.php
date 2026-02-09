<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// IMPORTANTE: Traemos el controlador que sí existe
use App\Http\Controllers\Api\ContentController; 

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// --- ESTA ES LA RUTA QUE FALTABA ---
Route::get('/v1/widget/{uuid}', [ContentController::class, 'getArticles']);
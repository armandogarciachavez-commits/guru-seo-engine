<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// IMPORTANTE: Usamos el NUEVO nombre
use App\Http\Controllers\Api\WidgetContentController; 

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Ruta apuntando al NUEVO controlador
Route::get('/v1/widget/{uuid}', [WidgetContentController::class, 'getArticles']);
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
// IMPORTANTE: Usamos el archivo que Git confirmó que existe
use App\Http\Controllers\Api\WidgetContentController; 

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Ruta apuntando al NUEVO controlador seguro
Route::get('/v1/widget/{uuid}', [WidgetContentController::class, 'getArticles']);
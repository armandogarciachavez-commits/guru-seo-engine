<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ContentController; // <--- ESTO ES VITAL

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// La ruta mágica para tu Widget
Route::get('/v1/widget/{uuid}', [ContentController::class, 'getArticles']);
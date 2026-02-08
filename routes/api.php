<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/feed/{project_id}', [\App\Http\Controllers\API\WidgetController::class, 'index']);
Route::get('/v1/projects/{uuid}/articles', [\App\Http\Controllers\Api\ArticleController::class, 'index']);

<?php

use Illuminate\Support\Facades\Route;

// 1. LA REGLA DE ORO: Si entran a la raíz, mándalos al Login
Route::get('/', function () {
    return redirect('/admin/login');
});

// 2. Ruta de Login (por si Filament la pide explícitamente)
Route::get('/login', function () {
    return redirect('/admin/login');
})->name('login');

// NOTA: No necesitamos definir rutas de Filament aquí.
// Filament lo hace automáticamente en segundo plano.
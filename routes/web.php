<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Página para probar WebSockets (Etapa 4)
Route::view('/inventario', 'inventario');

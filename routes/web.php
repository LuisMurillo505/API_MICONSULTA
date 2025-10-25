<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('Login',[AuthController::class,'Login']);

Route::get('/usuarios', [AuthController::class, 'index']);

Route::get('/puede-subir-archivos/{clinica_id}/{paciente_id}', [AuthController::class, 'puedeSubirArchivosPacientes']);

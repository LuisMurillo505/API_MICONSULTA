<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('Login',[AdminController::class,'Login']);

//admin
Route::middleware('api.key')->get('/conteo-datos', [AdminController::class, 'conteoDatos']);
Route::middleware('api.key')->get('/index-clinicas', [AdminController::class, 'index_clinicas']);
Route::middleware('api.key')->get('/detalle-clinica/{clinica_id}', [AdminController::class, 'detalle_clinica']);
Route::middleware('api.key')->get('/detalle-usuario/{usuario_id}', [AdminController::class, 'index_detalleusuario']);
Route::middleware('api.key')->get('/reportes', [AdminController::class, 'index_reportes']);


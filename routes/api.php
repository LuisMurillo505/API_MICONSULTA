<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicaPanel\AdminController;
use App\Http\Controllers\ClinicaPanel\MedicoController;
use App\Http\Controllers\ClinicaPanel\RecepcionController;
use App\Http\Controllers\ClinicaPanel\LoginController;

//login
Route::middleware('api.key')->post('/login', [LoginController::class, 'login']);
//admin
Route::middleware('api.key')->get('/index/{usuario_id}', [AdminController::class, 'index']);
//perfil
Route::middleware('api.key')->get('/perfilAdmin/{usuario_id}', [AdminController::class, 'index_perfil']);
//usuarios
Route::middleware('api.key')->get('/usuarios/{usuario_id}', [AdminController::class, 'index_usuarios']);
Route::middleware('api.key')->get('/crear-usuarios/{usuario_id}', [AdminController::class, 'index_crearUsuario']);

//medico
Route::middleware('api.key')->get('/medico/{usuario_id}', [MedicoController::class, 'index']);

//recepcion
Route::middleware('api.key')->get('/recepcion/{usuario_id}', [RecepcionController::class, 'index']);







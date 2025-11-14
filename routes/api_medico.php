<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicaPanel\AdminController;
use App\Http\Controllers\ClinicaPanel\MedicoController;
use App\Http\Controllers\ClinicaPanel\RecepcionController;
use App\Http\Controllers\ClinicaPanel\LoginController;

//medico
Route::middleware('api.key')->get('/medico/{usuario_id}', [MedicoController::class, 'index']);
Route::middleware('api.key')->get('/citasmedico/{usuario_id}', [MedicoController::class, 'index_citas']);
Route::middleware('api.key')->get('/calendariomedico/{usuario_id}', [MedicoController::class, 'index_calendario']);
//detallecita esta en admincontroller-adminPanel
Route::middleware('api.key')->get('/perfilmedico/{usuario_id}', [MedicoController::class, 'index_perfil']);


//recepcion
Route::middleware('api.key')->get('/recepcion/{usuario_id}', [RecepcionController::class, 'index']);







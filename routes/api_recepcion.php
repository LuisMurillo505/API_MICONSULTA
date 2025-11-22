<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicaPanel\AdminController;
use App\Http\Controllers\ClinicaPanel\MedicoController;
use App\Http\Controllers\ClinicaPanel\RecepcionController;
use App\Http\Controllers\ClinicaPanel\LoginController;

//recepcion
Route::middleware('api.key')->get('/recepcion/{usuario_id}', [RecepcionController::class, 'index']);
Route::middleware('api.key')->get('/crearcitas/{clinica_id}', [RecepcionController::class, 'index_createcita']);
Route::middleware('api.key')->get('/calendario/{usuario_id}', [RecepcionController::class, 'index_calendario']);
Route::middleware('api.key')->get('/citas/{usuario_id}', [RecepcionController::class, 'index_citas']);
//detallecita esta en admincontroller-adminPanel








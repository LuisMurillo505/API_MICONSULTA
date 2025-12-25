<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicaPanel\MedicoController;
use App\Http\Controllers\ClinicaPanel\NotificacionController;


//medico
Route::middleware('api.key')->get('/medico/{usuario_id}', [MedicoController::class, 'index']);
Route::middleware('api.key')->get('/citasmedico/{usuario_id}', [MedicoController::class, 'index_citas']);
Route::middleware('api.key')->get('/calendariomedico/{usuario_id}', [MedicoController::class, 'index_calendario']);
Route::middleware('api.key')->get('/notificacion-status/{notificacion_id}', [NotificacionController::class, 'update']);
Route::middleware('api.key')->get('/notificacion-delete/{notificacion_id}', [NotificacionController::class, 'delete']);
Route::middleware('api.key')->get('/notificacion-updateAll/{usuarioId}', [NotificacionController::class, 'marcarTodas']);
Route::middleware('api.key')->get('/notificacion-deleteAll/{usuarioId}', [NotificacionController::class, 'eliminarTodas']);
//detallecita esta en admincontroller-adminPanel







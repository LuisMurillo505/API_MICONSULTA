<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminPanel\AdminController;
use App\Http\Controllers\AdminPanel\PlanController;
use App\Http\Controllers\AdminPanel\TarifaStripeContoller;
use App\Http\Controllers\AdminPanel\UsuarioAdminController;

//admin
Route::middleware('api.key')->group(function () {
    Route::get('/conteo-datos', [AdminController::class, 'conteoDatos']);
    Route::get('/index-clinicas', [AdminController::class, 'index_clinicas']);
    Route::get('/detalle-clinica/{clinica_id}', [AdminController::class, 'detalle_clinica']);
    Route::get('/detalle-usuario/{usuario_id}', [AdminController::class, 'index_detalleusuario']);
    Route::get('/detalle-paciente/{paciente_id}', [AdminController::class, 'index_detallepaciente']);
    Route::get('/expediente/{paciente_id}', [AdminController::class, 'index_expediente']);
    Route::get('/detalle-cita/{cita_id}/{paciente_id}', [AdminController::class, 'index_detalleCita']);
    Route::get('/calendario', [AdminController::class, 'index_calendario']);
    Route::get('/usuariosAdmin', [AdminController::class, 'index_usuariosAdmin']);
    Route::get('/planes', [AdminController::class, 'index_planes']);
    Route::get('/detalle-plan/{plan_id}', [AdminController::class, 'detalle_plan']);
    Route::get('/stripetarifas', [AdminController::class, 'index_stripeTarifas']);
    Route::get('/reportes', [AdminController::class, 'index_reportes']);
    Route::get('/reportes-detalle/{plan_id}/{status_id}', [AdminController::class, 'detalle_reporte']);
});

//planes
Route::middleware('api.key')->post('/crear-plan', [PlanController::class, 'store']);
Route::middleware('api.key')->put('/actualizar-plan/{plan_id}', [PlanController::class, 'update']);
Route::middleware('api.key')->post('/crear-funcion', [PlanController::class, 'storeFunciones']);
Route::middleware('api.key')->put('/actualizar-funcion/{funcion_id}', [PlanController::class, 'updateFuncion']);

//TarifasStripe
Route::middleware('api.key')->post('/crear-tarifa', [TarifaStripeContoller::class, 'store']);
Route::middleware('api.key')->put('/actualizar-tarifa/{tarifa_id}', [TarifaStripeContoller::class, 'update']);

//usuariosAdmin
Route::middleware('api.key')->post('/crear-usuarioAdmin', [UsuarioAdminController::class, 'store']);







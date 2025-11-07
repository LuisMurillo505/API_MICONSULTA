<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicaPanel\AdminController;
use App\Http\Controllers\ClinicaPanel\LoginController;


Route::middleware('api.key')->post('/login', [LoginController::class, 'login']);
Route::middleware('api.key')->get('/index/{usuario_id}', [AdminController::class, 'index']);






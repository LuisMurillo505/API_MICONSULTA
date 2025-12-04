<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicaPanel\AdminController;
use App\Http\Controllers\ClinicaPanel\UsuariosController;
use App\Http\Controllers\ClinicaPanel\NotificacionController;
use App\Http\Controllers\ClinicaPanel\MedicoController;
use App\Http\Controllers\ClinicaPanel\RecepcionController;
use App\Http\Controllers\ClinicaPanel\LoginController;
use App\Http\Controllers\ClinicaPanel\SuscripcionController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

//login
Route::middleware('api.key')->post('/login', [LoginController::class, 'login']);
//register
Route::middleware('api.key')->post('/register', [SuscripcionController::class, 'register']);


//verificar correo
Route::get('/email/verify/{id}/{hash}', function (Illuminate\Http\Request $request) {

    $user = \App\Models\User::findOrFail($request->route('id'));

    if (!$user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }

    return redirect(config('app.frontend_url') . '/email/verificado');

})->middleware('signed')->name('verification.verify');

Route::get('/email/verify', function () {
    // return view('verify-email');
})->name('verification.notice');

//admin
Route::middleware('api.key')->get('/index/{usuario_id}', [AdminController::class, 'index']);
//perfil
Route::middleware('api.key')->get('/perfilAdmin/{usuario_id}', [AdminController::class, 'index_perfil']);
//usuarios
Route::middleware('api.key')->get('/usuarios/{usuario_id}', [AdminController::class, 'index_usuarios']);
Route::middleware('api.key')->get('/crear-usuarios/{usuario_id}', [AdminController::class, 'index_crearUsuario']);
Route::middleware('api.key')->put('/actualizar-usuarios/{usuario_id}/{foto}', [UsuariosController::class, 'update']);
Route::middleware('api.key')->post('/admin-medico/{usuario_id}', [UsuariosController::class, 'store_adminMedico']);

//pacientes
Route::middleware('api.key')->get('/pacientes/{usuario_id}', [AdminController::class, 'index_pacientes']);
Route::middleware('api.key')->get('/crear-pacientes', [AdminController::class, 'index_createpaciente']);
Route::middleware('api.key')->get('/buscarPaciente/{usuario_id}', [AdminController::class, 'buscarPaciente']);
//servicios
Route::middleware('api.key')->get('/servicios/{usuario_id}', [AdminController::class, 'index_servicios']);
//profesiones
Route::middleware('api.key')->get('/profesiones/{usuario_id}', [AdminController::class, 'index_profesiones']);
//citas
Route::middleware('api.key')->get('/citas/{usuario_id}', [AdminController::class, 'index_citas']);
Route::middleware('api.key')->get('/createcita/{clinica_id}', [AdminController::class, 'index_createcita']);
Route::middleware('api.key')->get('/calendario/{usuario_id}', [AdminController::class, 'index_calendario']);









<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicaPanel\AdminController;
use App\Http\Controllers\ClinicaPanel\UsuariosController;
use App\Http\Controllers\ClinicaPanel\PacientesController;
use App\Http\Controllers\ClinicaPanel\ServiciosController;
use App\Http\Controllers\ClinicaPanel\EspecialidadController;
use App\Http\Controllers\ClinicaPanel\CitasController;
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

//perfil admin
Route::middleware('api.key')->get('/perfilAdmin/{usuario_id}', [AdminController::class, 'index_perfil']);
Route::middleware('api.key')->put('/actualizar-clinica', [UsuariosController::class, 'update_clinica']);

//usuarios
Route::middleware('api.key')->get('/usuarios/{usuario_id}', [AdminController::class, 'index_usuarios']);
Route::middleware('api.key')->get('/crear-usuarios/{usuario_id}', [AdminController::class, 'index_crearUsuario']);
Route::middleware('api.key')->put('/actualizar-usuarios', [UsuariosController::class, 'update']);
Route::middleware('api.key')->post('/admin-medico/{usuario_id}', [UsuariosController::class, 'store_adminMedico']);
Route::middleware('api.key')->post('/registrar-usuario', [UsuariosController::class, 'store']);
Route::middleware('api.key')->put('/usuario-status/{usuario_id}', [UsuariosController::class, 'update_status']);
Route::middleware('api.key')->post('/cambiar-password/{usuario_id}', [UsuariosController::class, 'cambiarpassword']);
Route::middleware('api.key')->post('/restablecer-password', [UsuariosController::class, 'Restablecerpassword']);

//pacientes
Route::middleware('api.key')->get('/pacientes/{usuario_id}', [AdminController::class, 'index_pacientes']);
Route::middleware('api.key')->get('/crear-pacientes', [AdminController::class, 'index_createpaciente']);
Route::middleware('api.key')->get('/buscarPaciente/{usuario_id}', [AdminController::class, 'buscarPaciente']);
Route::middleware('api.key')->post('/registrar-paciente', [PacientesController::class, 'store']);
Route::middleware('api.key')->put('/actualizar-paciente', [PacientesController::class, 'update']);
Route::middleware('api.key')->post('/historiaclinica-paciente', [PacientesController::class, 'historialClinico']);
Route::middleware('api.key')->get('/observacion-paciente/{nota_id}', [PacientesController::class, 'deleteNote']);
Route::middleware('api.key')->get('/descargar-expediente/{paciente_id}/{cita_id}', [PacientesController::class, 'DescargarExpediente']);
Route::middleware('api.key')->post('/subirarchivos-paciente', [PacientesController::class, 'ArchivosPacientes']);
Route::middleware('api.key')->get('/descargararchivos-paciente/{archivo_id}', [PacientesController::class, 'descargarArchivo']);
Route::middleware('api.key')->get('/eliminararchivos-paciente/{archivo_id}', [PacientesController::class, 'destroy']);

//servicios
Route::middleware('api.key')->get('/servicios/{usuario_id}', [AdminController::class, 'index_servicios']);
Route::middleware('api.key')->post('/crear-servicio', [ServiciosController::class, 'store']);
Route::middleware('api.key')->put('/actualizar-servicio/{servicio_id}', [ServiciosController::class, 'update']);
Route::middleware('api.key')->get('/eliminar-servicio/{servicio_id}', [ServiciosController::class, 'delete']);
Route::middleware('api.key')->get('/reporte-servicios', [ServiciosController::class, 'descargarReporteServicios']);

//profesiones
Route::middleware('api.key')->get('/profesiones/{usuario_id}', [AdminController::class, 'index_profesiones']);
Route::middleware('api.key')->post('/crear-profesiones', [EspecialidadController::class, 'store']);
Route::middleware('api.key')->put('/actualizar-profesiones/{profesion_id}', [EspecialidadController::class, 'update']);

//citas
Route::middleware('api.key')->get('/citas/{usuario_id}', [AdminController::class, 'index_citas']);
Route::middleware('api.key')->get('/createcita/{clinica_id}', [AdminController::class, 'index_createcita']);
Route::middleware('api.key')->get('/calendario/{usuario_id}', [AdminController::class, 'index_calendario']);
Route::middleware('api.key')->post('/crear-cita', [CitasController::class, 'store']);
Route::middleware('api.key')->get('/disponibilidad-medico/{medico_id}', [CitasController::class, 'disponibilidad']);
Route::middleware('api.key')->put('/finalizar-cita/{cita_id}', [CitasController::class, 'update']);
Route::middleware('api.key')->get('/cancelar-cita/{cita_id}', [CitasController::class, 'cancelar']);
Route::middleware('api.key')->get('/reporte-citas', [CitasController::class, 'descargarReporteCitas']);











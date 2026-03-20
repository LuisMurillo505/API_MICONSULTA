<?php

use App\Http\Controllers\PacientePanel\AgendaPacienteController;
use App\Http\Controllers\InventarioPanel\AlmacenArticuloController;
use App\Http\Controllers\InventarioPanel\AlmacenesController;
use App\Http\Controllers\InventarioPanel\ArticulosController;
use App\Http\Controllers\InventarioPanel\CategoriasController;
use App\Http\Controllers\InventarioPanel\ConceptoMovimientoController;
use App\Http\Controllers\InventarioPanel\KardexController;
use App\Http\Controllers\InventarioPanel\MarcasController;
use App\Http\Controllers\InventarioPanel\MovimientosInvenarioController;
use App\Http\Controllers\InventarioPanel\SubcategoriaController;
use App\Http\Controllers\InventarioPanel\TipoMovimientoController;
use App\Http\Controllers\InventarioPanel\VentasController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClinicaPanel\AdminController;
use App\Http\Controllers\ClinicaPanel\UsuariosController;
use App\Http\Controllers\ClinicaPanel\PacientesController;
use App\Http\Controllers\ClinicaPanel\ServiciosController;
use App\Http\Controllers\ClinicaPanel\EspecialidadController;
use App\Http\Controllers\ClinicaPanel\CitasController;
use App\Http\Controllers\ClinicaPanel\GuiaConfiguracionController;
use App\Http\Controllers\ClinicaPanel\NotificacionController;
use App\Http\Controllers\ClinicaPanel\LoginController;
use App\Http\Controllers\ClinicaPanel\SuscripcionController;
use App\Http\Controllers\ClinicaPanel\RecetaController;
// use Illuminate\Foundation\Auth\EmailVerificationRequest;

//login
Route::middleware('api.key')->post('/login', [LoginController::class, 'login']);

//register
Route::middleware('api.key')->post('/register', [SuscripcionController::class, 'register']);

//ciudades
Route::middleware('api.key')->get('/ciudades', [LoginController::class, 'ciudades']);

//planes
Route::middleware('api.key')->get('/planes', [LoginController::class, 'planes']);

//suscripciones
Route::middleware('api.key')->get('/suscribirse', [SuscripcionController::class, 'checkout']);
Route::middleware('api.key')->get('/suscripcion/exito', [SuscripcionController::class, 'exito']);
Route::middleware('api.key')->get('/suscripcion/cancelado/{usuario_id}', [SuscripcionController::class, 'cancelado']);
Route::middleware('api.key')->post('/cancelar-suscripcion/{usuario_id}', [SuscripcionController::class, 'cancelarSuscripcion']);

Route::post('/stripe/webhook', [SuscripcionController::class, 'handleWebhook']);

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

Route::middleware('api.key')->get('/verificar-correo/{usuario_id}', [NotificacionController::class, 'verificarCorreo']);

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
Route::middleware('api.key')->put('/paciente/asignar', [PacientesController::class, 'AsignarCitaPaciente']);


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
Route::middleware('api.key')->post('/crear-citarapida', [CitasController::class, 'store_citarapida']);
Route::middleware('api.key')->get('/disponibilidad-medico/{medico_id}', [CitasController::class, 'disponibilidad']);
Route::middleware('api.key')->put('/finalizar-cita/{cita_id}', [CitasController::class, 'update']);
Route::middleware('api.key')->get('/cancelar-cita/{cita_id}', [CitasController::class, 'cancelar']);
Route::middleware('api.key')->get('/reporte-citas', [CitasController::class, 'descargarReporteCitas']);

//guia de configuracion
Route::middleware('api.key')->put('/actualizar-estado/{paso_id}/{usuario_id}', [GuiaConfiguracionController::class, 'actualizar_estado']);

//soporte
Route::middleware('api.key')->post('/enviar-soporte', [NotificacionController::class, 'EnviarCorreoSoporte']);

//token
Route::middleware('api.key')->get('/getToken/{usuario_id}', [LoginController::class, 'getToken']);

//recetas admin
Route::middleware('api.key')->post('/recetas', [RecetaController::class, 'store']);
Route::middleware('api.key')->get('/recetas', [AdminController::class, 'index_recetas']);
Route::middleware('api.key')->get('/recetas/{id}', [RecetaController::class, 'show']);


//inventarios
Route::middleware('auth:sanctum')->group(function () {
    
    // Este es tu endpoint /auth/me
    Route::get('/auth/me', [LoginController::class, 'me']);

    //status
    // Route::get('/status', [StatusController::class, 'index']);

    //almacenes
    Route::get('/almacenes', [AlmacenesController::class, 'index']);
    Route::post('/almacenes', [AlmacenesController::class, 'store']);
    Route::put('/almacenes/{almacen_id}', [AlmacenesController::class, 'update']);

    //tipo movimiento
    Route::get('/tipos-movimiento', [TipoMovimientoController::class, 'index']);

    //concepto movimientos
    Route::get('/conceptos-movimientos', [ConceptoMovimientoController::class, 'index']);

    //categorias
    Route::get('/categorias', [CategoriasController::class, 'index']);
    Route::get('/subcategorias', [SubcategoriaController::class, 'index']);
    Route::post('/categorias', [CategoriasController::class, 'store']);
    Route::put('/categorias/{categoria_id}', [CategoriasController::class, 'update']);

    //marcas
    Route::get('/marcas', [MarcasController::class, 'index']);
    Route::post('/marcas', [MarcasController::class, 'store']);
    Route::put('/marcas/{marca_id}', [MarcasController::class, 'update']);

    //articulos
    Route::get('/articulos', [ArticulosController::class, 'index']);
    // Route::get('/articulos/signed-urlimage/{articulo_id}', [ArticulosController::class, 'showArticuloImage']);
    Route::post('/articulos', [ArticulosController::class, 'store']);
    Route::put('/articulos/{articulo_id}', [ArticulosController::class, 'update']);
    Route::put('/articulos/{articulo_id}/{status_id}', [ArticulosController::class, 'updateStatus']);

    //almacen articulos
    Route::post('/almacen/articulos', [AlmacenArticuloController::class, 'store']);
    Route::get('/almacen/articulos', [AlmacenArticuloController::class, 'index']);

    //kardex
    Route::get('/kardexA', [KardexController::class, 'indexA']);
    Route::get('/kardexB', [KardexController::class, 'indexB']);
    Route::get('/kardexC', [KardexController::class, 'indexC']);
    Route::get('/kardexD', [KardexController::class, 'indexD']);

    //movimientos Inventario
    Route::get('/movimientos-inventario', [MovimientosInvenarioController::class, 'index']);

    //ventas
    Route::get('/ventas', [VentasController::class, 'index']);
    Route::get('/ventas/{usuario_id}', [VentasController::class, 'show']);
    Route::post('/ventas', [VentasController::class, 'store']);

});

//portal de pacientes
Route::middleware('api.key')->get('/calendariopaciente/{bookingSlugClinica}', [AgendaPacienteController::class, 'index']);
Route::middleware('api.key')->get('/calendariopaciente/{bookingSlugClinica}/{bookingSlugMedico}', [AgendaPacienteController::class, 'index']);
Route::middleware('api.key')->get('/agendarcitapaciente/{bookingSlugClinica}', [AgendaPacienteController::class, 'index_createcita']);
Route::middleware('api.key')->get('/agendarcitapaciente/{bookingSlugClinica}/{bookingSlugMedico}', [AgendaPacienteController::class, 'index_createcita']);










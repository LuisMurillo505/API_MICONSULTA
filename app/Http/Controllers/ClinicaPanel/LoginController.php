<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use App\Services\SuscripcionService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use App\Models\UsuarioAdmin;
use App\Models\Usuario;
use App\Models\Personal;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{

    protected $suscripcionService;

    public function __construct(SuscripcionService $suscripcionService)
    {
        $this->suscripcionService=$suscripcionService;
    }
/**
 * Inicia sesión de usuario mediante API.
 *
 * Este método valida las credenciales enviadas, verifica el estado de la suscripción,
 * determina el tipo de usuario (Administrador, Personal Médico o Recepción) y genera
 * un token de acceso válido para su uso en llamadas API.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Illuminate\Validation\ValidationException Si las credenciales no son válidas.
 */
    public function login(Request $request)
    {
        try {
            //Validación de entrada
            $validated = $request->validate([
                'correo' => 'required|email:rfc,dns',
                'password' => 'required|string',
            ]);

            //Buscar usuario con sus relaciones
            $usuario = Usuario::with(['clinicas.suscripcion', 'personal', 'clinicas'])
                ->where('correo', $validated['correo'])
                ->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Correo no registrado. Por favor, registra tu clínica.',
                    'error'=> 'Usuario_noRegistrado'
                ], 404);
            }

            //Eliminar sesiones anteriores (opcional si usas Sanctum)
            DB::table('sessions')->where('user_id', $usuario->id)->delete();

            //Verificar suscripción
            $verificarSub = $this->suscripcionService->verificarSuscripcion($usuario);

            if ($verificarSub['estado'] === 'vencido') {
                $mensaje = $verificarSub['es_personal'] === 'Personal Administrador'
                    ? 'Su plan actual ha llegado a su fecha de vencimiento. Por favor, renuévalo para continuar.'
                    : $verificarSub['mensaje'] . ', contacta al administrador para renovar el plan.' ;

                return response()->json([
                    'success' => false,
                    'verificarSub'=>$verificarSub,
                    'message' => $mensaje,
                    'error'=>'plan_vencido'
                ], 403);
            }

            // Obtener perfil del personal (si aplica)
            $perfil = Personal::with(['usuario', 'puesto'])
                ->whereHas('usuario', fn($q) => $q->where('id', $usuario->id))
                ->first();

            // Determinar tipo de acceso
            $rol = 'Personal Administrador';
            if ($perfil) {
                $rol = $perfil->puesto->descripcion;
            }

            // Actualizar última conexión
            $usuario->update(['last_connection' => now()]);

            // Generar token de acceso
            // $token = $usuario->createToken('auth_token', [$rol])->plainTextToken;

            // ✅ Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'message' => 'Inicio de sesión exitoso.',
                'data' => [
                    'usuario' => $usuario,
                    'rol' => $rol,
                    // 'token' => $token,
                ],
            ]);

        } catch (ValidationException $e) {
            // Errores de validación
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            // Errores generales
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al iniciar sesión.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request){

         // Cierra la sesión del usuario
        Auth::logout();

        //Invalidacion de la sesion actual, se destruye la informacion de la sesion y asegura que el autenticador ya no sea valido
        $request->session()->invalidate();

        // Regenera el token CSRF de la sesión
        $request->session()->regenerateToken();
        
        return redirect(route('login'));
    }
}

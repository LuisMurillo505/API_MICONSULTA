<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use App\Services\SuscripcionService;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Validation\ValidationException;
use App\Models\Usuario;
use App\Models\Ciudades;
use App\Models\Personal;
use App\Models\Planes;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{

    protected $suscripcionService;

    public function __construct(SuscripcionService $suscripcionService)
    {
        $this->suscripcionService=$suscripcionService;
    }

/**
 * Obtiene el listado completo de ciudades.
 *
 * Este método:
 * - Recupera todas las ciudades registradas en la base de datos.
 * - Retorna la información en formato JSON.
 *
 * @param \Illuminate\Http\Request $request
 *        Request HTTP (no requiere parámetros específicos).
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON con el listado de ciudades.
 *
 * @throws \Exception
 */
    public function ciudades(Request $request){
        try{
            // Obtener todas las ciudades
            $ciudades=Ciudades::all();

            return response()->json([
                'success'=>true,
                'data'=>compact('ciudades')
            ]);

         }catch (Exception $e) {
            // Errores generales
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error.',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

/**
 * Obtiene los planes disponibles del sistema.
 *
 * Este método:
 * - Recupera los planes principales (Estandar y Pro).
 * - Retorna la información en formato JSON.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON con los planes disponibles.
 *
 * @throws \Exception
 */
     public function planes(){
        try{    
            // Obtener planes principales
            $Estandar=Planes::where('nombre','Estandar')->first();
            $Pro=Planes::where('nombre','Pro')->first();

            return response()->json([
                'success'=>true,
                'data'=>compact('Estandar','Pro')
            ]);

        }catch(Exception $e){
            // Errores generales
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error.',
                'error' => $e->getMessage(),
            ], 500);
        }
        
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

}

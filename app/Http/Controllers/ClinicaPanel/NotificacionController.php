<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notificaciones;
use App\Models\Usuario;
use App\Models\Soporte;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Services\UsuarioService;

class NotificacionController extends Controller
{
     protected $usuarioService;
     protected $gcs;

    public function __construct(UsuarioService $usuarioServices) {
        $this->usuarioService = $usuarioServices;
    }

/**
 * Reenvía el correo de verificación a un usuario.
 *
 * Este método:
 * - Busca al usuario por su ID.
 * - Verifica que el usuario exista.
 * - Envía nuevamente la notificación de verificación de correo electrónico.
 *
 * @param int $usuario_id
 *        ID del usuario al que se le enviará el correo de verificación.
 *
 * @return \Illuminate\Http\JsonResponse|null
 *         Respuesta JSON en caso de error o null si el envío es exitoso.
 *
 * @throws \Exception
 */
    public function verificarCorreo(int $usuario_id){
        try{
            // Buscar usuario o lanzar excepción si no existe
            $usuario=Usuario::findOrFail($usuario_id);
            if(!$usuario){
                return response()->json([
                    'success' => false,
                    'message' => 'Correo no registrado. Por favor, registra tu clínica.',
                    'error'=> 'Usuario_noRegistrado'
                ], 404);
            }

            // Enviar notificación de verificación de correo
            $usuario->sendEmailVerificationNotification();

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al enviar el correo de verificación.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

/**
 * Marca una notificación como leída.
 *
 * Este método:
 * - Busca la notificación por su ID.
 * - Cambia su estado a inactivo (0).
 * - Guarda los cambios en la base de datos.
 *
 * @param int $notificacion_id
 *        ID de la notificación a actualizar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando el resultado de la operación.
 *
 * @throws \Exception
 */
    public function update(int $notificacion_id){

        try{
            // Buscar notificación
            $notificacion=Notificaciones::findOrFail($notificacion_id);

            // Marcar como leída
            $notificacion->estado=0;
            $notificacion->save();

            return response()->json(['ok'=>true]);
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
 * Elimina una notificación.
 *
 * Este método:
 * - Busca la notificación por su ID.
 * - Elimina el registro de la base de datos.
 *
 * @param int $notificacion_id
 *        ID de la notificación a eliminar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando el resultado de la operación.
 *
 * @throws \Exception
 */
    public function delete(int $notificacion_id){

        try{

            // Obtener notificación o lanzar excepción si no existe
            $notificacion=Notificaciones::findOrFail($notificacion_id);
            // Eliminar notificación
            $notificacion->delete();

            return response()->json(['ok'=>true]);
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
 * Marca como leídas todas las notificaciones de un usuario.
 *
 * Este método:
 * - Obtiene el usuario por su ID.
 * - Accede al personal asociado al usuario.
 * - Actualiza todas las notificaciones activas (estado = 1)
 *   marcándolas como leídas (estado = 0).
 *
 * @param int $usuarioId
 *        ID del usuario cuyas notificaciones serán marcadas como leídas.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando el resultado de la operación.
 *
 * @throws \Exception
 */
    public function marcarTodas(int $usuarioId)
    {
        try{
            // Obtener usuario
            $usuario=Usuario::findOrFail($usuarioId);
            // Marcar todas las notificaciones activas como leídas
            Notificaciones::where('personal_id', $usuario->personal->id)
                ->where('estado', 1)->update(['estado' => 0]);

            return response()->json(['ok' => true]);
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
 * Elimina todas las notificaciones asociadas a un usuario.
 *
 * Este método:
 * - Obtiene el usuario por su ID.
 * - Localiza las notificaciones relacionadas con el personal del usuario.
 * - Elimina todas las notificaciones sin importar su estado.
 *
 * @param int $usuarioId
 *        ID del usuario del cual se eliminarán todas las notificaciones.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando el resultado de la operación.
 *
 * @throws \Exception
 */
    public function eliminarTodas(int $usuarioId)
    {   
        try{
            // Obtener usuario
            $usuario=Usuario::findOrFail($usuarioId);
            // Eliminar todas las notificaciones del personal del usuario
            Notificaciones::where('personal_id', $usuario->personal->id)->delete();

            return response()->json(['ok' => true]);
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
 * Envía un correo de soporte técnico y registra el mensaje en la base de datos.
 *
 * Este método:
 * - Valida los datos enviados desde el formulario de soporte.
 * - Obtiene la información del usuario y su clínica.
 * - Envía un correo electrónico al área de soporte.
 * - Guarda el mensaje de soporte en la base de datos.
 *
 * @param \Illuminate\Http\Request $request
 *        Request que contiene la información del mensaje de soporte.
 *        Campos requeridos:
 *        - nombre
 *        - asunto
 *        - prioridad
 *        - mensaje
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando si el mensaje fue enviado correctamente.
 *
 * @throws \Exception
 */

    public function EnviarCorreoSoporte(Request $request){
        try{
            // Validación de los campos de entrada
            $data = $request->validate([
                'nombre' => 'required|string',
                'asunto' => 'required|string',
                'prioridad' => 'required|string',
                'mensaje' => 'required|string',
            ]);

            // Obtener datos del usuario y clínica
            $usuario=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Datos del correo
            $detalles = [
                'clinica_id'=>$usuario['clinica_id'],
                'correo_usuario'=>$usuario['correo'],
                'clinica'=>$usuario['nombre_clinica'],
                'nombre' => $data['nombre'],
                'asunto' => $data['asunto'],
                'prioridad' => $data['prioridad'],
                'mensaje' => $data['mensaje'],
            ];

            // Enviar correo al área de soporte
            Mail::send('emails.soporte', $detalles, function ($message) {
                $message->to('soporte@miconsulta.mx', 'Soporte Técnico')
                        ->subject('Nuevo mensaje de soporte');
            });

            // Guardar en base de datos
            Soporte::create([
                'clinica_id'=>$usuario['clinica_id'],
                'clinica'=>$usuario['nombre_clinica'],
                'nombre' => $data['nombre'],
                'asunto' => $data['asunto'],
                'prioridad' => $data['prioridad'],
                'mensaje' => $data['mensaje'],
            ]);

            return response()->json([
                'success'=>true,
                'message'=>'Tu mensaje fue enviado con éxito.'
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


}

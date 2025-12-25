<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use App\Models\Especialidad;
use App\Models\Personal;
use App\Services\UsuarioService;
use App\Services\PlanService;
use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\Clinicas;
use App\Services\RegisterLoginService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Exception;
use Illuminate\Support\Facades\Log;
/**
 * AdminController - Controlador para gestionar la administracion
 */

class UsuariosController extends Controller 
{
    protected $usuarioService;
    protected $planService;
    
    public function __construct(
        UsuarioService $usuarioService, PlanService $planServices)
    {
        $this->usuarioService = $usuarioService;
        $this->planService = $planServices;
    }

    
/**
 * Crea un nuevo usuario (con su información personal) en el sistema.
 *
 * Este método realiza las siguientes validaciones y operaciones:
 *  - Verifica que las contraseñas coincidan.
 *  - Verifica que el correo no esté registrado previamente.
 *  - Verifica que la clínica no haya alcanzado el límite de usuarios según su plan.
 *  - Crea el registro en la tabla `usuarios`.
 *  - Crea el registro asociado en la tabla `personal`.
 *  - Opcionalmente guarda la disponibilidad del personal si se envían los días.
 *
 * @param  \Illuminate\Http\Request  $request  Datos de la solicitud:
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Exception  Si ocurre cualquier error no controlado durante el proceso de creación
 */
    public function store(Request $request){
        try{
            
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Validar coincidencia de contraseñas
            if($request->password!=$request->confirm_password){
                return response()->json([
                    'success' => false,
                    'message' => 'Contaseña no coinciden',
                    'error'=> 'password_incorrecta'
                ], 404);        
            }

            // Verificar si el correo ya existe
            $check_usuario=Usuario::where('correo','=',$request->correo)->first();

            if($check_usuario){
                return response()->json([
                        'success' => false,
                        'message' => 'Correo ya existe',
                        'error'=> 'usuario_yaExiste'
                    ], 404); 
                }

            // Verificar límite de usuarios por plan/clínica
            if(!$this->planService->puedeCrearUsuario($datos['clinica_id'])){
               return response()->json([
                    'success' => false,
                    'message' => 'Limite de usuarios alcanzados',
                    'error'=> 'LIMITE_ALCANZADO'
                ], 404); 
            }   

            // Crear usuario
            $usuario=Usuario::create([
                'clinica_id'=>$datos['clinica_id'],
                'correo' => $request->correo,
                'password' => Hash::make($request->password),  
                'status_id'=>1,
                'created_at'=>now(),
                'update_at'=>now()
            ]);

            // Crear registro de personal asociado
            $personal=Personal::create([
                'nombre' => $request->nombre,
                'apellido_paterno' => $request->apellido_paterno,
                'apellido_materno' => $request->apellido_materno,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'especialidad_id' => $request->especialidad,
                'cedula_profesional' => $request->cedula_profesional,
                'telefono' => $request->telefono,
                'puesto_id' => $request->puesto,
                'foto'=>$request->foto ?? null,
                'usuario_id'=>$usuario->id,
                'created_at'=>now(),
                'updated_at'=>now()
            ]);

            // Guardar disponibilidad si se enviaron días
            if($request->input('dias')){
                $this->usuarioService->disponibilidad($request->input('dias'),$personal->id);
            }

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'message' => 'Usuario Creado con Exito.',
                'data' => [
                    'usuario' => $usuario,
                    'personal'=>$personal
                ],
            ]);

        }catch (Exception $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   

        }
    
    }   

/**
 * Crea un registro de personal médico (médico/admin) asociado a un usuario ya existente.
 *
 * Este método es utilizado por el administrador de la clínica para agregar la información
 * del médico/personal que ya tiene una cuenta de usuario creada previamente.
 * Permite crear una nueva especialidad/profesión si se envía el campo "profesion",
 * o usar una especialidad existente si se envía "especialidad".
 *
 * @param  \Illuminate\Http\Request  $request    Datos del formulario
 *
 * @param  int|string  $usuario_id  ID del usuario (de la tabla usuarios) al que se asociará este registro de personal
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Exception  Si ocurre cualquier error durante la creación del personal o especialidad
 */
    public function store_adminMedico(Request $request,int $usuario_id){
        try{
    
            // Obtener datos del usuario autenticado/admin para saber a qué clínica pertenece
            $datos=$this->usuarioService->DatosUsuario($usuario_id);
 
            // Si viene "profesion", crear una nueva especialidad en la clínica
            if($request->profesion){
                $profesion=Especialidad::create([
                    'clinica_id'=>$datos['clinica_id'],
                    'descripcion'=>$request->profesion,
                    'status_id'=> 1
                ]);
                $profesion=$profesion->id;
            }else{
                $profesion=$request->especialidad;
            }
           
            // Crear el registro en la tabla personal (médico o admin médico)
            $personal=Personal::create([
                'nombre' => $request->nombre,
                'apellido_paterno' => $request->apellido_paterno,
                'apellido_materno' => $request->apellido_materno,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'especialidad_id' =>  $profesion ?? null,
                'cedula_profesional' =>  null,
                'telefono' => $datos['clinica']['telefono'],
                'puesto_id' => 3,
                'foto'=>null,
                'usuario_id'=>$usuario_id,
                'created_at'=>now(),
                'updated_at'=>now()
            ]);        

             // Retorna los datos en formato JSON
            return response()->json([
                'success'=>true,
            ]);

        } catch (Exception $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   

        }
    
    }

/**
 * Actualiza la información del personal/médico asociado a un usuario.
 *
 * Este método permite modificar los datos profesionales del médico o personal
 * (especialidad, cédula, teléfono, foto y disponibilidad horaria).
 * El registro se busca mediante el `usuario_id` enviado en la solicitud.
 *
 * @param  \Illuminate\Http\Request  $request
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Exception  Si ocurre algún error durante la actualización o guardado de disponibilidad
 */
     public function update(Request $request){
        try {
        
            // Buscar el registro de 'personal' por su ID
            $personal = Personal::where('usuario_id',$request->usuario_id)->first();
    
            // Verificar si el registro 'personal' fue encontrado
            if (!$personal) {
                // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrió un error al actualizar el usuario',
                    'error' => 'usuario no existe',
                ], 500);               
             }
        
              // Actualizar los datos del usuario
                $personal->update([
                    'especialidad_id' => $request->especialidad,
                    'cedula_profesional' => $request->cedula_profesional,
                    'telefono' => $request->telefono,
                    'foto' => $request->foto ?? null, // Foto actualizada si fue subida
                    'updated_at' => now()
                ]);

            //guardar disponibilidad si se proporciona
            if($request->input('dias')){
                $this->usuarioService->update_disponibilidad($request->input('dias'),$personal->id);
            }
             
            // Retorna los datos en formato JSON
            return response()->json([
                'success'=>true,
            ]);

        } catch (Exception $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);            
        }
    }

/**
 * Actualiza la información de la clínica y su dirección asociada.
 *
 * Este método permite al usuario autenticado (administrador) modificar los datos
 * básicos de su clínica: nombre, teléfono, RFC, foto y dirección completa.
 *
 * @param  \Illuminate\Http\Request  $request
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Illuminate\Validation\ValidationException  Si fallan las reglas de validación
 * @throws \Exception  En caso de error al guardar en base de datos
 */
    public function update_clinica(Request $request){
        try{
            // Validación de los datos de entrada
            $validated=$request->validate([
                'nombre_clinica'=>'required|string',
                'telefono_clinica'=>'numeric',
                'direccion.calle'=>'nullable|string',
                'direccion.localidad'=>'nullable|string',
                'direccion.ciudad'=>'nullable|string',
                'rfc'=>'nullable|string',
            ]);

            // Obtener los datos del usuario para identificar su clínica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Buscar la clínica del usuario
            $clinica=Clinicas::find($datos['clinica_id']);

            if (!$clinica) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró la clínica asociada a este usuario',
                    'error'   => 'clinica_not_found'
                ], 404);
            }

            // Actualizar datos principales de la clínica
            $clinica->update([
                'nombre'=>$validated['nombre_clinica'],
                'telefono'=>$validated['telefono_clinica'],
                'RFC'=>$validated['rfc'] ?? null,
                'foto'=>$request->foto ?? null,
            ]);

            // Actualizar dirección 
            $clinica->direccion()->update([
                'calle' =>$validated['direccion']['calle'],
                'localidad' =>$validated['direccion']['localidad'],
                'ciudad' =>$validated['direccion']['ciudad'],
            ]);

            // Retorna los datos en formato JSON
            return response()->json([
                'success'=>true,
            ]);

        }catch (Exception $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);         
        }
    }
 
/**
 * Actualiza el estado (status_id) de un usuario entre activo/inactivo,
 * verificando previamente que la clínica no exceda el límite permitido
 * de usuarios activos según el plan contratado.
 *
 * - Si el usuario está activo (status_id = 1), se desactiva.
 * - Si el usuario está inactivo (status_id != 1):
 *      - Se valida si la clínica aún puede activar más usuarios.
 *      - Si supera el límite permitido, retorna error.
 *      - Si no supera el límite, se activa.
 *
 * @param int $user_id  ID del usuario a actualizar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Retorna un JSON indicando éxito o error en la operación.
 *
 * @throws \Exception Captura cualquier error inesperado durante el proceso.
 */
    public function update_status(int $user_id){
        try{
            // Obtener usuario y su clínica
            $usuario=Usuario::find($user_id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el usuario',
                    'error'   => 'usuario_not_found'
                ], 404);
            }
            $clinica=$usuario->clinicas->id;

            // Obtener número permitido de usuarios según el plan
            $usuariosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) use($clinica) {
            $query->where('funcion_id', 2);
            }])->where('id',$clinica)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',2);
            })->first();

            $permitidos=$usuariosPermitidos->suscripcion->plan->funciones_planes->cantidad;

            // Contar usuarios activos en esa clínica
            $conteoUsuarios=Personal::whereHas('usuario',function($q) use($clinica){
                $q->where('clinica_id',$clinica)
                    ->where('status_id',1);
            })->count();

            // Desactivar usuario si ya está activo
            if($usuario->status_id==1){

                $usuario->update([
                    'status_id'=>2
                ]);
                return response()->json([
                    'success'=>true,
                    'message'=>'Usuario actualizado correctamente'
                ]);
            }else{
                // Validar límite permitido de usuarios activos
                if($permitidos<=$conteoUsuarios){
                    return response()->json([
                        'success' => false,
                        'error'=>'LIMITE_ALCANZADO',
                        'message' => 'Limite Alcanzado',
                    ], 404); 
                }
                // Activar usuario
                $usuario->update([
                    'status_id'=>1
                ]);
                return response()->json([
                    'success'=>true,
                    'message'=>'Usuario actualizado correctamente'
                ]);            
            } 
           
        }catch(Exception $e){
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);  
        }
    }   

/**
 * Cambia la contraseña de un usuario validando primero su contraseña actual.
 *
 * Proceso:
 * - Valida los campos: contraseña actual, nueva y confirmación.
 * - Verifica que la contraseña actual coincida con la registrada.
 * - Si coincide, actualiza la contraseña con la nueva.
 * - Si no coincide, retorna error.
 *
 * @param \Illuminate\Http\Request $request
 *        Contiene la contraseña actual, nueva y confirmación.
 *
 * @param int $usuario_id
 *        ID del usuario cuyo password será modificado.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o el motivo del error.
 *
 * @throws \Exception
 *         Captura cualquier error inesperado durante la ejecución.
 */
    public function cambiarpassword(Request $request,int $usuario_id){
        try{

            // Validación de datos del request
            $validated=$request->validate([
                'contraseña_actual'=>'required|string',
                'nueva_contraseña'=>'required|string',
                'confirmar_contraseña'=>'required|string|same:nueva_contraseña'
            ]);

            // Buscar usuario
            $usuario=Usuario::findOrFail($usuario_id);
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el usuario',
                    'error'   => 'usuario_not_found'
                ], 404);
            }

            // Verificar contraseña actual
            if(Hash::check($validated['contraseña_actual'], $usuario->password ?? null)){
                 // Actualizar contraseña
                $usuario->update([
                    'password'=>hash::make($validated['nueva_contraseña'])
                ]);
                 return response()->json([
                    'success'=>true,
                    'message'=>'Contraseña actualizada con exito'
                ]); 
            }else{
                return response()->json([
                    'success'=>false,
                    'message'=>'Contraseña actual incorrecta'
                ]);             
            }
          

        }catch(Exception $e){
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);         
        }
    }

/**
 * Restablece la contraseña de un usuario generando una contraseña temporal
 * y enviándola por correo electrónico.
 *
 * Proceso:
 * - Valida el correo recibido.
 * - Verifica si el correo corresponde a un usuario registrado.
 * - Genera una contraseña temporal.
 * - Actualiza la contraseña del usuario con la nueva temporal hasheada.
 * - Envía un correo al usuario con la contraseña temporal.
 *
 * @param \Illuminate\Http\Request $request
 *        Contiene el correo del usuario que solicita el restablecimiento.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error según el resultado.
 *
 * @throws \Exception
 *         Captura cualquier excepción generada durante el proceso.
 */
    public function Restablecerpassword(Request $request){

        try{
            // Validación del correo
            $validated=$request->validate([
                'email'=>'required|email'
            ]);
            
            // Buscar usuario por correo
           $usuario=Usuario::where('correo',$validated['email'])->first();

           if(!$usuario){
                return response()->json([
                    'success'=>false,
                    'message'=>'Correo no registrado'
                ]); 
           }

            // Generar contraseña temporal
           $temporalpassword=Str::random(10);

            // Actualizar contraseña del usuario
           $usuario->update([
             'password'=> Hash::make($temporalpassword)
           ]);

            // Enviar correo con la contraseña temporal
            Mail::to($usuario->correo)->send(new \App\Mail\TemporaryPasswordMail($usuario, $temporalpassword));

           return response()->json([
                'success'=>true,
                'message'=>'Se envio una contraseña temporal a tu correo'
            ]); 

        }catch(Exception $e){
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);  
        }
    }
    
}

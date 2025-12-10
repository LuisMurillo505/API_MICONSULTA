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

    public function store(Request $request){
        try{
            
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            if($request->password!=$request->confirm_password){
                return response()->json([
                    'success' => false,
                    'message' => 'Contaseña no coinciden',
                    'error'=> 'password_incorrecta'
                ], 404);        
            }

            $check_usuario=Usuario::where('correo','=',$request->correo)->first();

            if($check_usuario){
                return response()->json([
                        'success' => false,
                        'message' => 'Correo ya existe',
                        'error'=> 'usuario_yaExiste'
                    ], 404); 
                }

            if(!$this->planService->puedeCrearUsuario($datos['clinica_id'])){
               return response()->json([
                    'success' => false,
                    'message' => 'Limite de usuarios alcanzados',
                    'error'=> 'LIMITE_ALCANZADO'
                ], 404); 
            }   

            $usuario=Usuario::create([
                'clinica_id'=>$datos['clinica_id'],
                'correo' => $request->correo,
                'password' => Hash::make($request->password),  
                'status_id'=>1,
                'created_at'=>now(),
                'update_at'=>now()
            ]);

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

            //guardar disponibilidad si se proporciona
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
            // log::error($e->getMessage());   
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   

        }
    
    }   

    public function store_adminMedico(Request $request,$usuario_id){
        try{
    

            $datos=$this->usuarioService->DatosUsuario($usuario_id);
 
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
            Log::error($e->getMessage());
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   

        }
    
    }

     /**
     * Actualizar los datos de un usuario
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

    public function update_clinica(Request $request){
        try{
            $validated=$request->validate([
                'nombre_clinica'=>'required|string',
                'telefono_clinica'=>'numeric',
                'direccion.calle'=>'nullable|string',
                'direccion.localidad'=>'nullable|string',
                'direccion.ciudad'=>'nullable|string',
                'rfc'=>'nullable|string',
            ]);

            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            $clinica=Clinicas::find($datos['clinica_id']);

            $clinica->update([
                'nombre'=>$validated['nombre_clinica'],
                'telefono'=>$validated['telefono_clinica'],
                'RFC'=>$validated['rfc'] ?? null,
                'foto'=>$request->foto ?? null,
            ]);

            $clinica->direccion()->update([
                'calle' =>$validated['direccion']['calle'],
                'localidad' =>$validated['direccion']['localidad'],
                'ciudad' =>$validated['direccion']['ciudad'],
            ]);

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
     * Cambiar estado de usuario entre Activo/Inactivo
     */
    public function update_status($user_id){
        try{
            $usuario=Usuario::find($user_id);
            $clinica=$usuario->clinicas->id;

            //checar usuarios permitidos
            $usuariosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes.funcion' => function ($query) {
                $query->where('nombre', 'usuarios');
            }])->where('id',$clinica)
            ->whereHas('suscripcion.plan.funciones_planes.funcion',function($q) {
                $q->where('nombre','usuarios');
            })->first();

            $permitidos=$usuariosPermitidos->suscripcion->plan->funciones_planes->cantidad;

            $conteoUsuarios=Personal::whereHas('usuario',function($q) use($clinica){
                $q->where('clinica_id',$clinica)
                    ->where('status_id',1);
            })->count();

            if($usuario->status_id==1){

                $usuario->update([
                    'status_id'=>2
                ]);
                return response()->json([
                    'success'=>true,
                    'message'=>'Usuario actualizado correctamente'
                ]);
            }else{
                if($permitidos<=$conteoUsuarios){
                    return response()->json([
                        'success' => false,
                        'message' => 'Limite Alcanzado',
                    ], 404); 
                }
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
     * Cambiar contraseña de usuario
     */

    public function cambiarpassword(Request $request,$usuario_id){
        try{

            $validated=$request->validate([
                'contraseña_actual'=>'required|string',
                'nueva_contraseña'=>'required|string',
                'confirmar_contraseña'=>'required|string|same:nueva_contraseña'
            ]);

            $usuario=Usuario::find($usuario_id);

            if(Hash::check($validated['contraseña_actual'], $usuario->password ?? null)){
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

    public function Restablecerpassword(Request $request){

        try{
            $validated=$request->validate([
                'email'=>'required|email'
            ]);
            
           $usuario=Usuario::where('correo',$validated['email'])->first();

           if(!$usuario){
                return response()->json([
                    'success'=>false,
                    'message'=>'Correo no registrado'
                ]); 
           }

           $temporalpassword=Str::random(10);

           $usuario->update([
             'password'=> Hash::make($temporalpassword)
           ]);

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

    // public function actualizar_plan(Request $request){
    //     try{

    //         Stripe::setApiKey(config('services.stripe.secret'));
             
    //         // Valida las credenciales del usuario
    //         $user=auth()->user();

    //         // Valida las credenciales del usuario
    //         $validated = $request->validate([
    //             'plan'=>'required|string',
    //         ]);

    //         //obtener plan
    //         $plan = Planes::where('nombre',$validated['plan'])->first();

    //         $clinica= Clinicas::find($user->clinica_id);

    //         // Crear el PaymentIntent con return_url incluido
    //         $paymentIntent = PaymentIntent::create([
    //             'amount' => intval($plan->precio * 100),
    //             'currency' => 'mxn', //0.1 x 100 = 10
    //             'payment_method' => $request->payment_method,
    //             'confirmation_method' => 'manual',
    //             'confirm' => true,
    //             'receipt_email' => $user->correo,
    //             'return_url' => route('payment.success')
    //         ]);

    //          // Verificar si se necesita más acción del cliente (ej. autenticación)
    //             if ($paymentIntent->status === 'requires_action' &&
    //                 $paymentIntent->next_action->type === 'redirect_to_url') {
    //                 // Redirigir al cliente a completar autenticación
    //                 return redirect($paymentIntent->next_action->redirect_to_url->url);
    //             }

    //         // Guardar en base de datos si el pago está confirmado
    //         if ($paymentIntent->status === 'succeeded') {
    //             $payment = Payment::create([
    //                 'payment_id' => $paymentIntent->id,
    //                 'amount' => $paymentIntent->amount,
    //                 'currency' => $paymentIntent->currency,
    //                 'status' => $paymentIntent->status,
    //                 'email' => $user->correo,
    //             ]);

    //             // Mail::to($request->email)->send(new PaymentConfirmation($payment));
    //         }

    //         $clinica->update([
    //             'plan_id'=>$plan->id,
    //             'inicio_plan'=> now()
    //         ]);


    //         return redirect()->route('admin.perfil')->with('success','Plan actualizado Correctamente');
    //     }catch(Exception $e){
    //         return back()->with('error', 'Error: ' . $e->getMessage());
    //     }
       
    // }
    
    
}

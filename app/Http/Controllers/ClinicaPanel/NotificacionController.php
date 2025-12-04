<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notificaciones;
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

    //verificarCorreo
    // public function verificarCorreo(EmailVerificationRequest $request){
    //     $request->fulfill();
    //     $user=auth()->user();
    //     if($user->personal){
    //         if($user->personal->puesto_id===2){
    //             return redirect('/medico');
    //         }elseif($user->personal->puesto_id===1){
    //             return redirect('/calendario');
    //         }else{
    //             if($user->clinicas->suscripcion->plan->nombre!=='Gratuito'){
    //                 $checkoutSession = app(\App\Services\SuscripcionService::class)
    //                 ->checkoutSession(
    //                     $user->clinicas->suscripcion->plan,
    //                     $user->clinicas->stripe_customer_id
    //                 );

    //                 return redirect($checkoutSession->url);
    //             }
                
    //             return redirect('/miconsulta');
    //         }
    //     }
         
    //     return redirect('/miconsulta');
    // }

    public function update($notificacion_id){

        $notificacion=Notificaciones::find($notificacion_id);
        $notificacion->estado=0;
        $notificacion->save();

        return response()->json(['ok'=>true]);
    }

     /**
     * Elimina una notificación específica.
     */
    public function delete($notificacion_id){

        $notificacion=Notificaciones::find($notificacion_id);
        $notificacion->delete();

        return response()->json(['ok'=>true]);
    }

   public function marcarTodas()
    {
        $personalId = auth()->user()->personal->id;
        Notificaciones::where('personal_id', $personalId)->where('estado', 1)->update(['estado' => 0]);

        return response()->json(['ok' => true]);
    }

    public function eliminarTodas()
    {
        $personalId = auth()->user()->personal->id;
        Notificaciones::where('personal_id', $personalId)->delete();

        return response()->json(['ok' => true]);
    }

    // public function EnviarCorreoSoporte(Request $request){
    //     try{
    //         $data = $request->validate([
    //             'nombre' => 'required|string',
    //             'asunto' => 'required|string',
    //             'prioridad' => 'required|string',
    //             'mensaje' => 'required|string',
    //         ]);

    //         $usuario=$this->usuarioService->DatosUsuario();

    //         // Datos del correo
    //         $detalles = [
    //             'clinica_id'=>$usuario['clinica_id'],
    //             'correo_usuario'=>$usuario['correo'],
    //             'clinica'=>$usuario['nombre_clinica'],
    //             'nombre' => $data['nombre'],
    //             'asunto' => $data['asunto'],
    //             'prioridad' => $data['prioridad'],
    //             'mensaje' => $data['mensaje'],
    //         ];

    //         Mail::send('emails.soporte', $detalles, function ($message) {
    //             $message->to('soporte@miconsulta.mx', 'Soporte Técnico')
    //                     ->subject('Nuevo mensaje de soporte');
    //         });

    //          // Guardar en base de datos
    //         Soporte::create([
    //             'clinica_id'=>$usuario['clinica_id'],
    //             'clinica'=>$usuario['nombre_clinica'],
    //             'nombre' => $data['nombre'],
    //             'asunto' => $data['asunto'],
    //             'prioridad' => $data['prioridad'],
    //             'mensaje' => $data['mensaje'],
    //         ]);

    //         return back()->with('success', 'Tu mensaje fue enviado con éxito.');
    //     }catch(Exception $e){

    //     }
    // }


}

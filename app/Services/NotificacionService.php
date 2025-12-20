<?php
namespace App\Services; 

use App\Models\Notificaciones;
use App\Models\Citas;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificacionService{
    public Function crear_cita(int $personal_id,int $cita_id){
        try{
            $mensaje='Se agendo una nueva cita: '.$cita_id;
            Notificaciones::create([
                'personal_id'=>$personal_id,
                'mensaje'=>$mensaje,
                'estado'=> 1,
                'created_at'=>now(),
                'updated_at'=>now(),
                
            ]);
        }catch(Exception $e){
              \Log::error($e);
        }
    }
    public Function finalizar_cita(int $personal_id,int $cita_id){
        try{
            $mensaje='Se finalizo la cita: '.$cita_id;
            Notificaciones::create([
                'personal_id'=>$personal_id,
                'mensaje'=>$mensaje,
                'estado'=> 1,
                'created_at'=>now(),
                'updated_at'=>now(),
                
            ]);
        }catch(Exception $e){
              \Log::error($e);
        }
    }

    public Function cancelar_cita(int $personal_id,int $cita_id){
        try{
            $mensaje='Se cancelo la cita: '.$cita_id;
            Notificaciones::create([
                'personal_id'=>$personal_id,
                'mensaje'=>$mensaje,
                'estado'=> 1,
                'created_at'=>now(),
                'updated_at'=>now(),
                
            ]);
        }catch(Exception $e){
              \Log::error($e);
        }
    }

    public function notificar_cita($personal_id){
        try{
           $citas=Citas::Where('personal_id',$personal_id)
            ->where('status_id',1)
            ->whereDate('fecha_cita',now())->get();
            $notificaciones=Notificaciones::where('personal_id',$personal_id)->get();

            $mensaje="";
           foreach($citas as $cita){
                $mensaje="Recordatorio de cita de hoy: ".$cita->id;
                $verificar=$notificaciones->contains('mensaje',$mensaje);
                if(!$verificar){
                    Notificaciones::create([
                        'personal_id'=>$personal_id,
                        'mensaje'=>$mensaje,
                        'estado'=> 1,
                        'created_at'=>now(),
                        'updated_at'=>now(),
                    ]);
                }   
            }
          

        }catch(Exception $e){
              \Log::error($e);
        }
    }

    // public function enviarCorreoMedico()
    // {

    //    $medico_citas = Citas::whereHas('personal.usuario.clinicas.suscripcion.plan',function($q) {
    //         $q->where('nombre','Estandar');
    //      })
    //     ->where('status_id',1)
    //     ->whereDate('fecha_cita', now()->toDateString())
    //     ->with(['personal.usuario.clinicas','servicio','paciente'])
    //     ->orderBy('hora_inicio')
    //     ->get();
        
    
    //     $citasPorMedico = $medico_citas->groupBy('personal_id');

    //     foreach($citasPorMedico as $medicoId => $citas){
    //         $medico=$citas->first()->personal;
    //         $clinica=$medico->usuario->clinicas->nombre;


    //         Mail::to($medico->usuario->correo)->send(new \App\Mail\CitaMail( $citas,$clinica));

    //     }
      
    // }

}
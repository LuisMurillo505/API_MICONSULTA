<?php
namespace App\Services; 

use App\Models\Notificaciones;
use App\Models\Citas;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificacionService{

/**
 * Crea una notificación del sistema cuando se agenda una nueva cita.
 *
 * Registra una notificación asociada al personal (médico) indicando
 * que se ha agendado una nueva cita. La notificación se guarda como
 * no leída (estado = 1).
 *
 * @param int $personal_id ID del personal (médico) que recibirá la notificación.
 * @param int $cita_id ID de la cita recién creada.
 *
 * @return void
 */
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

/**
 * Crea una notificación cuando se agenda una nueva cita.
 *
 * Registra una notificación asociada al personal indicado,
 * indicando que se ha agendado una nueva cita.
 *
 * @param int $personal_id ID del personal (médico) que recibirá la notificación
 * @param int $cita_id ID de la cita agendada
 *
 * @return void
 */
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

/**
 * Crea una notificación de sistema cuando una cita es cancelada.
 *
 * Este método registra una notificación asociada a un miembro del personal,
 * indicando que una cita específica ha sido cancelada. La notificación se
 * guarda con estado activo (no leída).
 *
 * @param int $personal_id ID del personal (médico) que recibirá la notificación.
 * @param int $cita_id ID de la cita cancelada.
 *
 * @return void
 */
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


/**
 * Genera notificaciones de recordatorio para las citas del día actual.
 *
 * Este método obtiene todas las citas activas (status_id = 1) del médico/personal
 * indicado cuya fecha corresponda al día de hoy.  
 * Por cada cita, verifica si ya existe una notificación con el mismo mensaje
 * para evitar duplicados.  
 * Si no existe, crea una nueva notificación con estado activo.
 *
 * @param int $personal_id ID del personal (médico) al que pertenecen las citas.
 *
 * @return void
 *
 * @throws \Exception En caso de error, la excepción es registrada en el log
 *                    pero no se propaga.
 */
    public function notificar_cita(int $personal_id){
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

/**
 * Envía correos electrónicos a los médicos de las clinicas con plan "Estandar"
 * que tengan citas programadas para el día actual.
 *
 * El correo incluye:
 * - Listado de citas del día
 * - Información del paciente
 * - Servicio agendado
 * - Nombre de la clínica
 *
 * Flujo:
 * 1. Obtiene todas las citas activas (status_id = 1) del día actual
 *    cuyos médicos pertenecen a clínicas con plan "Estandar".
 * 2. Agrupa las citas por médico.
 * 3. Envía un correo a cada médico con sus citas del día.
 *
 * @return void
 */
    public function enviarCorreoMedico()
    {

       $medico_citas = Citas::whereHas('personal.usuario.clinicas.suscripcion.plan',function($q) {
            $q->where('nombre','Estandar');
         })
        ->where('status_id',1)
        ->whereDate('fecha_cita', now()->toDateString())
        ->with(['personal.usuario.clinicas','servicio','paciente'])
        ->orderBy('hora_inicio')
        ->get();
        
    
        $citasPorMedico = $medico_citas->groupBy('personal_id');

        foreach($citasPorMedico as $medicoId => $citas){
            $medico=$citas->first()->personal;
            $clinica=$medico->usuario->clinicas->nombre;


            Mail::to($medico->usuario->correo)->send(new \App\Mail\CitaMail( $citas,$clinica));

        }
      
    }

}
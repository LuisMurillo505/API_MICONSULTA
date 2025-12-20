<?php

namespace App\Services;

use App\Models\Clinicas;
use App\Models\ArchivosPaciente;
use App\Models\Personal;
use App\Models\Pacientes;
use App\Models\Servicio;
use App\Models\Citas;
use Exception;
class PlanService{

    public function puedeCrearUsuario($clinica_id){
        try{
            $usuariosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 2);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',2);
            })->first();

            $permitidos=$usuariosPermitidos->suscripcion->plan->funciones_planes->cantidad ?? null;

            $conteoUsuarios=Personal::whereHas('usuario',function($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id)
                    ->where('status_id',1);
            })->count();

            return is_null($permitidos) || $permitidos>$conteoUsuarios;

        }catch(Exception $e){
            throw $e;
        }
    }

    public function puedeCrearPaciente($clinica_id){
        try{
            $pacientesPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 3);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',3);
            })->first();

            //conteo pacientes por clinica
            $conteoPacientes=Pacientes::where('clinica_id',$clinica_id)->count();

            $limite = $pacientesPermitidos->suscripcion->plan->funciones_planes->cantidad ?? null;

           return is_null($limite) || $limite > $conteoPacientes;

        }catch(Exception $e){
            throw $e;
        }
    }

    public function puedeCrearServicio($clinica_id){
        try{
            $serviciosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 1);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',1);
            })->first();

            $permitidos=$serviciosPermitidos->suscripcion->plan->funciones_planes->cantidad ?? null;

            $conteoServicios=Servicio::where('clinica_id',$clinica_id)
                ->where('status_id',1)->count();

            return is_null($permitidos) || $permitidos>$conteoServicios;

        }catch(Exception $e){

        }
    }

    public function puedeCrearCita(int $clinica_id): bool
    {
        try{
            $clinica = Clinicas::with(['suscripcion.plan.funciones_planes' => function ($q) {
            $q->where('funcion_id', 4);
            }])
            ->where('id', $clinica_id)
            ->first();

            $limite = $clinica->suscripcion->plan->funciones_planes->cantidad;

            $conteoCitas=citas::whereHas('personal.usuario',function($q) use($clinica_id){
                    $q->where('clinica_id',$clinica_id);
                })->count();

            return is_null($limite) || $limite > $conteoCitas;
        }catch(Exception $e){
            throw $e;
        }
       
    }
/**
 * Verifica si una clínica puede subir más archivos para un paciente según su plan actual.
 *
 * Este método evalúa si el plan de suscripción asociado a una clínica permite
 * subir archivos de pacientes (función con ID 5) y si el límite permitido no ha sido superado.
 *
 * @param  int  $clinica_id  ID de la clínica asociada al usuario.
 * @param  int  $paciente_id ID del paciente cuyos archivos se desean validar.
 *     Retorna un arreglo con:
 *     - `puede_subir`: verdadero si puede subir más archivos o si no hay límite definido.
 *     - `limite`: número máximo de archivos permitidos (null si no hay límite).
 *     - `subidos`: número de archivos ya subidos por el paciente.
 *
 * @throws \Throwable
 *     Lanza una excepción si ocurre un error durante las consultas o si la clínica no tiene plan activo.
 */    
public function puedeSubirArchivosPacientes($clinica_id,$paciente_id){
        try{
            $archivosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 5);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',5);
            })->first();

            // Conteo de archivos subidos por el paciente en la clínica
            $conteoArchivos=ArchivosPaciente::whereHas('paciente',function ($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id);
            })->where('paciente_id',$paciente_id)
            ->count();


            $limite = $archivosPermitidos->suscripcion->plan->funciones_planes->cantidad ?? null;

           return [
                'puede_subir' => is_null($limite) || $limite > $conteoArchivos,
                'limite'      => $limite,
                'subidos'     => $conteoArchivos,
            ];

        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Verifica si una clínica tiene habilitada la integración con Google Calendar según su plan.
 *
 * Este método consulta la suscripción activa de la clínica y determina
 * si el plan contratado incluye la función con `funcion_id = 6`, la cual
 * representa el acceso a Google Calendar.
 *
 * @param  int  $clinica_id  ID de la clínica que se desea verificar.
 * @return \App\Models\Clinicas|null
 *     Retorna la instancia de la clínica con sus relaciones si tiene acceso a Google Calendar,
 *     o `null` si no lo tiene habilitado.
 *
 * @throws \Throwable
 *     Lanza una excepción si ocurre un error durante la consulta.
 */
    public function puedeUsarGoogleCalendar($clinica_id){
        try{    
            $googleCalendar=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 6);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',6);
            })->first();

            return $googleCalendar ?? null;

        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Calcula cuántos usuarios adicionales puede registrar una clínica según su plan de suscripción.
 *
 * Este método obtiene la cantidad máxima de usuarios permitidos (función con `funcion_id = 2`)
 * definida en el plan actual de la clínica y compara ese límite con el número actual de usuarios activos.
 *
 * @param  int  $clinica_id       
 * @param  int  $conteoUsuarios    
 * @return int|null             
 *                                
 * @throws \Throwable    
 */
    public function usuariosPermitidos($clinica_id,$conteoUsuarios){
         try{    
            $usuariosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
            $query->where('funcion_id', 2);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',2);
            })->first();
            

            if($usuariosPermitidos->suscripcion->plan->funciones_planes->cantidad > $conteoUsuarios){
                $conteoUsuariosP= $usuariosPermitidos->suscripcion->plan->funciones_planes->cantidad - $conteoUsuarios;

            }else{
                $conteoUsuariosP = null;
            }

            return $conteoUsuariosP;

        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Calcula cuántos servicios adicionales puede registrar una clínica según su plan de suscripción.
 *
 * Este método obtiene la cantidad máxima de servicios permitidos (función con `funcion_id = 1`)
 * definida en el plan actual de la clínica y la compara con el número actual de servicios activos.
 *
 * @param  int  $clinica_id      
 * @param  int  $conteoServicios   
 * @return int|null                                                 
 *
 * @throws \Throwable           
 */
    public function serviciosPermitidos($clinica_id,$conteoServicios){
         try{    
           $serviciosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
            $query->where('funcion_id', 1);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',1);
            })->first();


            if($serviciosPermitidos->suscripcion->plan->funciones_planes->cantidad > $conteoServicios){
                $conteoServiciosP= $serviciosPermitidos->suscripcion->plan->funciones_planes->cantidad - $conteoServicios;
            }else{
                $conteoServiciosP = null;
            }

            return $conteoServiciosP;

        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Determina cuántos pacientes adicionales puede registrar una clínica según su plan de suscripción.
 *
 * Este método obtiene el límite de pacientes definidos en la función con `funcion_id = 3`
 * dentro del plan activo de la clínica y lo compara con el número actual de pacientes registrados.
 *
 * - Si el plan permite pacientes ilimitados (`cantidad` es `null`), retorna `"Ilimitado"`.
 * - Si aún no se alcanza el límite, retorna la cantidad restante disponible.
 * - Si ya se alcanzó el límite, retorna `null`.
 *
 * @param  int  $clinica_id         
 * @param  int  $conteoPacientes   
 * @return int|string|null          
 *                                  
 * @throws \Throwable               
 */
    public function pacientesPermitidos($clinica_id,$conteoPacientes){
         try{    
            $pacientesPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 3);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',3);
            })->first();

            if($pacientesPermitidos->suscripcion->plan->funciones_planes->cantidad == null){
                $conteoPacientesP = 'Ilimitado';
            }elseif($pacientesPermitidos->suscripcion->plan->funciones_planes->cantidad > $conteoPacientes){
                $conteoPacientesP= $pacientesPermitidos->suscripcion->plan->funciones_planes->cantidad - $conteoPacientes;
            }else{
                $conteoPacientesP = null;
            }

            return $conteoPacientesP;

        }catch(Exception $e){
            throw $e;
        }
    }
    
/**
 * Determina cuántas citas adicionales puede registrar una clínica según su plan de suscripción.
 *
 * Este método obtiene el límite de citas configurado en la función con `funcion_id = 4`
 * dentro del plan activo de la clínica y lo compara con la cantidad actual de citas registradas.
 *
 * - Si el plan permite citas ilimitadas (`cantidad` es `null`), retorna `"Ilimitado"`.
 * - Si aún hay espacio disponible dentro del límite, retorna la cantidad restante de citas.
 * - Si ya se alcanzó el límite, retorna `null`.
 *
 * @param  int  $clinica_id      
 * @param  int  $conteoCitas     
 * @return int|string|null      
 *
 * @throws \Throwable            
 */
    public function citasPermitidos($clinica_id,$conteoCitas){
         try{    
            $citasPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 4);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',4);
            })->first();

            if($citasPermitidos->suscripcion->plan->funciones_planes->cantidad == null){
                $conteoCitasP = 'Ilimitado';
            }elseif($citasPermitidos->suscripcion->plan->funciones_planes->cantidad > $conteoCitas){
                $conteoCitasP= $citasPermitidos->suscripcion->plan->funciones_planes->cantidad - $conteoCitas;
            }else{
                $conteoCitasP = null;
            }

            return $conteoCitasP;

        }catch(Exception $e){
            throw $e;
        }
    }
}
 
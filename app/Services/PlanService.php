<?php

namespace App\Services;

use App\Models\Clinicas;
use App\Models\ArchivosPaciente;
use App\Models\Personal;
use App\Models\Pacientes;
use App\Models\Funciones_planes;
use App\Models\Servicio;
use App\Models\Citas;
use Exception;
class PlanService{

/**
 * Verifica si una clínica aún puede crear nuevos usuarios según su plan de suscripción.
 *
 * Obtiene el límite de usuarios permitido desde las funciones del plan activo
 * (función con ID = 2) y lo compara contra el número actual de usuarios activos
 * registrados en la clínica.
 *
 * Si el plan no tiene un límite definido (cantidad = null), se permite crear usuarios
 * de forma ilimitada.
 *
 * @param int $clinica_id ID de la clínica a validar
 * @return bool Retorna true si la clínica puede crear más usuarios, false si alcanzó el límite
 *
 * @throws \Exception Si ocurre un error durante la consulta
 */
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

/**
 * Verifica si una clínica puede crear un nuevo paciente según
 * el límite definido en su plan de suscripción.
 *
 * El método consulta la suscripción activa de la clínica y valida
 * si el plan tiene asociada la función de creación de pacientes
 * (funcion_id = 3). Si el plan no tiene límite definido, se permite
 * crear pacientes de forma ilimitada.
 *
 * @param int $clinica_id ID de la clínica a evaluar.
 *
 * @return bool Retorna true si la clínica puede crear más pacientes,
 *              o false si ha alcanzado el límite permitido.
 *
 * @throws \Exception Si ocurre un error durante la consulta a la base de datos.
 */
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

/**
 * Verifica si una clínica puede crear un nuevo servicio según su plan de suscripción.
 *
 * Este método valida si el plan activo de la clínica permite crear más servicios,
 * comparando el límite definido en las funciones del plan contra la cantidad de
 * servicios activos actualmente registrados en la clínica.
 *
 * - La función evaluada es la asociada al `funcion_id = 1` (crear servicios).
 * - Si el plan no define un límite (`cantidad = null`), se permite crear servicios sin restricción.
 *
 * @param int $clinica_id ID de la clínica a validar.
 *
 * @return bool Retorna true si la clínica puede crear más servicios, false en caso contrario.
 *
 * @throws \Exception Si ocurre un error durante la consulta o validación.
 */
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
            throw $e;
        }
    }

/**
 * Verifica si la clínica aún puede crear nuevas citas según
 * el límite definido en su plan de suscripción.
 *
 * La validación se basa en:
 * - La función asociada al plan (funcion_id = 4 → creación de citas).
 * - El número total de citas registradas para la clínica.
 *
 * Si el plan no tiene límite definido (cantidad = null),
 * se permite crear citas de forma ilimitada.
 *
 * @param int $clinica_id ID de la clínica a validar.
 * @return bool Retorna true si puede crear más citas, false si alcanzó el límite.
 *
 * @throws \Exception Si ocurre un error durante la consulta.
 */
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
    public function usuariosPermitidos(int $clinica_id,int $conteoUsuarios){
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
    public function serviciosPermitidos(int $clinica_id,int $conteoServicios){
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
    public function pacientesPermitidos(int $clinica_id,int $conteoPacientes){
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
    public function citasPermitidos(int $clinica_id,int $conteoCitas){
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

/**
 * Desactiva usuarios de una clínica cuando el nuevo plan contratado
 * permite menos usuarios que el plan actual.
 *
 * Este método compara el límite de usuarios permitido entre el plan actual
 * y el nuevo plan. Si el nuevo plan tiene un límite menor y la clínica
 * excede ese límite, se desactivan los usuarios más recientes hasta
 * cumplir con la nueva restricción.
 *
 * Flujo general:
 * - Obtiene la clínica y su plan actual.
 * - Cuenta los usuarios activos de la clínica.
 * - Obtiene el límite de usuarios del plan actual y del nuevo plan.
 * - Si el nuevo plan permite menos usuarios y el total activo excede el límite,
 *   se desactivan los usuarios más recientes.
 *
 * @param int $clinica_id  ID de la clínica a evaluar.
 * @param int $plan_nuevo  ID del nuevo plan que se asignará a la clínica.
 *
 * @return void|null
 *         Retorna null si no es necesario desactivar usuarios
 *         o si la clínica no existe.
 *
 * @throws \Exception En caso de error durante el proceso.
 */
    public function desactualizar_usuarios(int $clinica_id,int $plan_nuevo){

        try{

            // Obtener la suscripción actual de la clínica
            $clinica=Clinicas::find($clinica_id);


            // Manejo del caso donde no hay suscripción
            if (!$clinica) {
                return null; 
            }

            //Obtenemos el id del plan actual
            $plan_actual=$clinica->suscripcion->plan_id;

            //usuarios activos de la clinica
            $conteoUsuarios=Personal::whereHas('usuario',function($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id)
                    ->where('status_id',1);
            })->count();


            //Comparación de planes
            $funciones_actual=Funciones_planes::where('plan_id',$plan_actual)->
                    where('funcion_id',2)->first();

            $funciones_nuevo=Funciones_planes::where('plan_id',$plan_nuevo)
                    ->where('funcion_id',2)->first();



            //comparar las cantidade
            if($funciones_actual->cantidad<=$funciones_nuevo->cantidad){
                return null;
            }else{

                if($conteoUsuarios>$funciones_nuevo->cantidad){

                    //Diferencia de usuarios entre el plan nuevo y plan actual
                    $diferencia=$conteoUsuarios-$funciones_nuevo->cantidad;

                    //obtenemos el personal de la clinica segun la diferencia de usuarios
                    $personal_clinica=Personal::whereHas('usuario',function($q) use($clinica_id){
                        $q->where('clinica_id',$clinica_id)
                            ->where('status_id',1);
                    })->with('usuario')
                    ->orderBy('created_at','desc')
                    ->limit($diferencia)
                    ->get();

                    //actualizar el estado a inactivo
                    foreach($personal_clinica as $personal){
                        if($personal->usuario){
                            $personal->usuario->status_id=2;
                            $personal->usuario->save();
                        }
                    }

                }else{
                    return null;
                }
                
            }
        }catch(Exception $e){
           throw $e;
        }
    }

/**
 * Desactiva servicios de una clínica cuando el nuevo plan contratado
 * permite menos servicios activos que el plan anterior.
 *
 * Este método compara el límite de servicios permitido entre el plan actual
 * y el nuevo plan. Si el nuevo plan permite menos servicios y la clínica
 * tiene más servicios activos de los permitidos, se desactivan los servicios
 * más recientes hasta cumplir con el nuevo límite.
 *
 * @param int $clinica_id ID de la clínica a evaluar.
 * @param int $plan_nuevo ID del nuevo plan contratado.
 *
 * @return void|null Retorna null si no es necesario desactivar servicios.
 *
 * @throws \Exception Si ocurre un error durante el proceso.
 */
    public function desactualizar_servicios(int $clinica_id,int $plan_nuevo){

        try{

            // Obtener la suscripción actual de la clínica
            $clinica=Clinicas::find($clinica_id);

            // Manejo del caso donde no hay suscripción
            if (!$clinica) {
                return null; 
            }

            //Obtenemos el id del plan actual
            $plan_actual=$clinica->suscripcion->plan_id;

            //usuarios activos de la clinica
            $conteoServicios=Servicio::where('clinica_id',$clinica_id)
            ->where('status_id',1)->count();

            //Comparación de planes
            $funciones_actual=Funciones_planes::where('plan_id',$plan_actual)->
                    where('funcion_id',1)->first();

            $funciones_nuevo=Funciones_planes::where('plan_id',$plan_nuevo)
                    ->where('funcion_id',1)->first();

            //comparar las cantidade
            if($funciones_actual->cantidad<=$funciones_nuevo->cantidad){
                return null;
            }else{

                if($conteoServicios>$funciones_nuevo->cantidad){

                    //Diferencia de usuarios entre el plan nuevo y plan actual
                    $diferencia=$conteoServicios-$funciones_nuevo->cantidad;

                    //obtenemos el personal de la clinica segun la diferencia de usuarios
                    $servicios_clinica=Servicio::where('clinica_id',$clinica_id)
                        ->where('status_id',1)
                        ->orderBy('created_at','desc')
                        ->limit($diferencia)
                        ->get();

                    //actualizar el estado a inactivo
                    foreach($servicios_clinica as $servicios){
                        if($servicios){
                            $servicios->status_id=2;
                            $servicios->save();
                        }
                    }

                }else{
                    return null;
                }
                
            }
        }catch(Exception $e){
           throw $e;
        }
    }

/**
 * Desactiva archivos de pacientes cuando el nuevo plan contratado
 * tiene un límite menor de archivos permitidos por paciente.
 *
 * Este método compara el plan actual de la clínica con el nuevo plan.
 * Si el nuevo plan permite menos archivos (función_id = 5), se desactivan
 * los archivos más recientes por paciente hasta cumplir el nuevo límite.
 *
 * La desactivación se realiza cambiando el status_id a 2.
 *
 * @param int $clinica_id ID de la clínica a evaluar.
 * @param int $plan_nuevo ID del nuevo plan contratado.
 *
 * @return void|null Retorna null si no hay cambios que aplicar.
 *
 * @throws \Exception Si ocurre un error durante el proceso.
 */
    public function desactualizar_archivos(int $clinica_id,int $plan_nuevo){

        try{

            // Obtener la suscripción actual de la clínica
            $clinica=Clinicas::find($clinica_id);

            // Manejo del caso donde no hay suscripción
            if (!$clinica) {
                return null; 
            }

            //Obtenemos el id del plan actual
            $plan_actual=$clinica->suscripcion->plan_id;

            //Comparación de planes
            $funciones_actual=Funciones_planes::where('plan_id',$plan_actual)->
                    where('funcion_id',5)->first();

            $funciones_nuevo=Funciones_planes::where('plan_id',$plan_nuevo)
                    ->where('funcion_id',5)->first();

            //comparar las cantidade
            if($funciones_actual->cantidad<=$funciones_nuevo->cantidad){
                return null;
            }

            $pacientes=Pacientes::where('clinica_id',$clinica_id)->get();

            foreach($pacientes as $p){
                $conteoarchivos=ArchivosPaciente::where('paciente_id',$p->id)
                    ->count();

                if($conteoarchivos>$funciones_nuevo->cantidad){

                    //Diferencia de usuarios entre el plan nuevo y plan actual
                    $diferencia=$conteoarchivos-$funciones_nuevo->cantidad;

                    //obtenemos el personal de la clinica segun la diferencia de usuarios
                    $archivos_paciente=ArchivosPaciente::where('paciente_id',$p->id)
                    ->orderBy('created_at','desc')
                    ->limit($diferencia)
                    ->get();

                    //actualizar el estado a inactivo
                    foreach($archivos_paciente as $ap){
                        if($ap){
                            $ap->status_id=2;
                            $ap->save();
                        }
                    }

                }else{
                    return null;
                }
                
            }
            
        }catch(Exception $e){
           throw $e;
        }
    }

/**
 * Verifica el estado de las suscripciones de todas las clínicas.
 *
 * Recorre todas las clínicas registradas y calcula los días restantes
 * de su suscripción activa. Si los días restantes superan el límite
 * negativo permitido por el plan (días de espera), la suscripción
 * se marca como inactiva.
 *
 * Lógica:
 * - Obtiene todas las clínicas.
 * - Para cada clínica:
 *   - Obtiene el plan asociado a su suscripción.
 *   - Calcula los días restantes de vigencia.
 *   - Si los días restantes son menores o iguales al negativo
 *     de los días de espera del plan, se desactiva la suscripción.
 *
 * @throws \Exception Si ocurre algún error durante el proceso.
 *
 * @return void
 */
    public function verificarSuscripciones(){
        try{
            $clinicas=Clinicas::all();
        
            foreach($clinicas as $clinica){
                $plan=$clinica->suscripcion->plan;
                $dias_restantes=$clinica->suscripcion->getDiasRestantes();

                if($dias_restantes<=-$plan->dias_espera){
                    $clinica->suscripcion->update([
                        'status_id'=>2
                    ]);
                }

            }
        }catch(Exception $e){
            throw $e;
        }
    }

}
 
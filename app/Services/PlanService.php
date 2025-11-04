<?php

namespace App\Services;

use App\Models\Clinicas;
use App\Models\ArchivosPaciente;
use Exception;
class PlanService{
    //checar si se pueden subir archivos de los pacientes
    public function puedeSubirArchivosPacientes($clinica_id,$paciente_id){
        try{
            $archivosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 5);
            }])->where('id',$clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes',function($q) {
                $q->where('funcion_id',5);
            })->first();

            //conteo pacientes por clinica
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

        }
    }
}
 
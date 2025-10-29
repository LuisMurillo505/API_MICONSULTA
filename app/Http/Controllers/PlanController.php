<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use App\Models\Clinicas;
use App\Models\ArchivosPaciente;

class PlanController extends Controller
{
     public function puedeSubirArchivosPacientes($clinica_id, $paciente_id)
    {
        try {
            // Obtener plan con la función 5
            $archivosPermitidos = Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 5);
            }])
            ->where('id', $clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes', function ($q) {
                $q->where('funcion_id', 5);
            })
            ->first();

            if (!$archivosPermitidos) {
                return response()->json([
                    'error' => 'No se encontró la clínica o no tiene configurado el plan correctamente.',
                ], 404);
            }

            // Conteo de archivos del paciente en esa clínica
            $conteoArchivos = ArchivosPaciente::whereHas('paciente', function ($q) use ($clinica_id) {
                $q->where('clinica_id', $clinica_id);
            })
            ->where('paciente_id', $paciente_id)
            ->count();

            $limite = $archivosPermitidos->suscripcion->plan->funciones_planes->cantidad ?? null;

            return response()->json([
                'puede_subir' => is_null($limite) || $limite > $conteoArchivos,
                'limite'      => $limite,
                'subidos'     => $conteoArchivos,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Ocurrió un error al procesar la solicitud.',
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }
}

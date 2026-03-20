<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Models\Receta;
use App\Models\Personal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecetaController extends Controller
{
    public function store(Request $request){
        try{
            $validated = $request->validate([
                'paciente_id' => 'required|exists:paciente,id',
                'usuario_id' => 'required|exists:usuario,id',
                'cita_id' => 'nullable|exists:cita,id',
                'expires_at' => 'nullable|date|after:today',
                'fecha' => 'nullable|date',
                'diagnostico'  => 'nullable|string',
                'items'      => 'required|array|min:1',
                'items.*.medicamento_nombre' => 'required|string|max:255',
                'items.*.dosis'        => 'required|string',
                'items.*.frecuencia'     => 'required|string',
                'items.*.duracion'      => 'required|string',
            ]);

            DB::beginTransaction();

            $receta = Receta::create([
                'folio'      => 'RX-' . strtoupper(Str::random(8)),
                'cita_id' => $validated['cita_id'] ?? null,
                'paciente_id' => $validated['paciente_id'],
                'personal_id'  => Personal::where('usuario_id',$validated['usuario_id'])->first()->id, // El médico es el usuario logueado
                'diagnostico'  => $validated['diagnostico'],
                'expires_at' => $validated['expires_at'],
                'fecha' => $validated['fecha'],
            ]);

            $receta->recetaDetalle()->createMany($validated['items']);

            DB::commit();

            return response()->json([
                'success' => true,
            ]); 

        }catch(Exception $e){
            DB::rollBack();
             return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $receta_id){
        try{
            $receta = Receta::with(['recetaDetalle','paciente','personal.especialidad'])->findOrFail($receta_id);

            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'receta'
                )
            ]);
        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

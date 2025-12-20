<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Models\Especialidad;
use App\Services\UsuarioService;
use App\Models\ProgresoUsuarioGuia;

class EspecialidadController extends Controller
{

    protected $usuarioService;
    
    public function __construct(UsuarioService $usuarioServices)
    {
        $this->usuarioService = $usuarioServices;
    }
/**
 * Registra una nueva especialidad (profesión) para una clínica.
 *
 * Este método:
 * - Obtiene la clínica asociada al usuario autenticado.
 * - Valida los datos de entrada.
 * - Crea la especialidad con estado activo.
 * - Marca el progreso del usuario si es la primera especialidad creada.
 *
 * @param \Illuminate\Http\Request $request
 *        Request que contiene la descripción de la especialidad.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON con la especialidad creada.
 *
 * @throws \Exception
 */
    public function store(Request $request){
        try{

            //obtiene el usuario autentificado y la clinica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            //validacion de campos de entrada
            $validated=$request->validate([
                'descripcionE'=>'required|string'
            ]);

            //crear especialidad
            $especialidad=Especialidad::create([
                'clinica_id'=>$datos['clinica_id'],
                'descripcion'=>$request->input('descripcionE'),
                'status_id'=>1,
                'created_at'=>now(),
                'updated_at'=>now()
            ]);

            // Marcar el paso completado si se inserta por primera vez
            if (Especialidad::count() >= 1){
                ProgresoUsuarioGuia::where('usuario_id', $datos['usuario_id'])
                    ->where('clave_paso', 'Agregar_profesion_modal')
                    ->update(['esta_completado' => true]);
            }

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'data'=>compact('especialidad')
            ]);

            
        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
       
    }

/**
 * Cambia el estado de una especialidad (activar / desactivar).
 *
 * Este método alterna el valor de `status_id`:
 * - 1 → Activo
 * - 2 → Inactivo
 *
 * @param int $especialidad_id
 *        ID de la especialidad a actualizar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando el resultado de la operación.
 *
 * @throws \Exception
 */
    public function update(int $especialidad_id){
        try{
            // Obtener especialidad o lanzar excepción si no existe
            $especialidad=Especialidad::findOrFail($especialidad_id);

            // Actualizar estado de la especialidad
            $especialidad->update([
                'status_id' => $especialidad->status_id === 1 ? 2 : 1
            ]);
            
            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
            ]);

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

/**
 * Elimina una especialidad por su ID.
 *
 * Este método:
 * - Busca la especialidad por su identificador.
 * - Si existe, la elimina de la base de datos.
 *
 * @param int $especialidad_id
 *        ID de la especialidad a eliminar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando el resultado de la operación.
 *
 * @throws \Exception
 */
    public function delete(int $especialidad_id){
        try{
            // Obtener especialidad o lanzar excepción si no existe
            $especialidad=Especialidad::findOrFail($especialidad_id);

            // Eliminar especialidad
            $especialidad->delete();

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
            ]);
        
           
        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
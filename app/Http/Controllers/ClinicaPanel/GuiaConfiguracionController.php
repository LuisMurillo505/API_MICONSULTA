<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use App\Models\PasoGuia;
use App\Models\ProgresoUsuarioGuia;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GuiaConfiguracionController extends Controller
{
/**
 * Marca un paso de la guía como completado para un usuario.
 *
 * Este método:
 * - Verifica que el usuario exista.
 * - Obtiene el paso de la guía por su ID.
 * - Busca el progreso del usuario asociado a ese paso.
 * - Marca el paso como completado.
 *
 * @param int $paso_id
 *        ID del paso de la guía que se desea marcar como completado.
 *
 * @param int $usuario_id
 *        ID del usuario al que pertenece el progreso.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando el resultado de la operación.
 *
 * @throws \Exception
 */
   public function actualizar_estado(int $paso_id,int $usuario_id)
    {
        try {

            // Verificar usuario
            $usuario=Usuario::findOrFail($usuario_id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Verificar que el paso existe
            $paso = PasoGuia::findOrFail($paso_id);
            $clavePaso = $paso->clave_paso;

            // Buscar el progreso correspondiente
            $progreso = ProgresoUsuarioGuia::where('usuario_id', $usuario->id)
                ->where('clave_paso', $clavePaso)
                ->first();

            if (!$progreso) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró progreso para este paso'
                ], 404);
            }

            // Marcar paso como completado
            $progreso->esta_completado = true;
            $progreso->save();

            return response()->json([
                'success' => true,
                'message' => 'Paso marcado como completado correctamente.'
            ]);
        } catch (\Exception $e) {
            // \Log::error('Error al actualizar paso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el paso',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Reiniciar el progreso de la guía
     */
    /* public function reiniciarProgreso()
    {
        try {
            $usuarioId = Auth::id();

            ProgresoUsuarioGuia::where('usuario_id', $usuarioId)->delete();

            return response()->json([
                'exito' => true,
                'mensaje' => 'Progreso de la guía reiniciado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'exito' => false,
                'mensaje' => 'Error al reiniciar el progreso',
                'error' => $e->getMessage()
            ], 500);
        }
    } */
}

<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Exception;
use App\Services\UsuarioService;
use App\Models\Citas;


class RecepcionController extends Controller
{
    protected $usuarioService;

     /**
     * Constructor que inyecta el servicio de usuarios.
     */
    public function __construct(UsuarioService $usuarioServices){
        $this->usuarioService=$usuarioServices;    
    }  

/**
 * Calcula diversos conteos de citas asociadas a la clínica del usuario.
 *
 * Esta función obtiene los datos del usuario (incluyendo su clinica_id)
 * y realiza diferentes conteos de citas según su estado:
 *  - Total de citas
 *  - Citas activas (status_id = 1)
 *  - Citas finalizadas (status_id = 3)
 *  - Citas canceladas (status_id = 4)
 *
 * Todos los conteos se basan únicamente en citas pertenecientes a la misma clínica
 * del usuario.
 *
 * @param  int $usuario_id  ID del usuario autenticado.
 * @return array            Arreglo con conteos específicos de citas.
 */
    public function conteoDatos(int $usuario_id){
        
        try{
            // Obtener información del usuario: contiene clinica_id, personal_id, etc.
            $datos=$this->usuarioService->datosUsuario($usuario_id);

            //Conteo total de citas asociadas a la clínica del usuario.
            $conteoCitas=citas::whereHas('personal.usuario',function($q) use($datos){
                $q->where('clinica_id',$datos['clinica_id']);
            })->count();

            //Citas activas (status_id = 1)
            $conteoActivas = citas::whereHas('personal.usuario', function($q) use($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })->where('status_id', 1)->count();

            //Citas finalizadas (status_id = 3)
            $conteoFinalizadas = citas::whereHas('personal.usuario', function($q) use($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })->where('status_id', 3)->count();

            //Citas canceladas (status_id = 4)
            $conteoCanceladas = citas::whereHas('personal.usuario', function($q) use($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })->where('status_id', 4)->count();

            // Retornar todos los conteos como arreglo asociativo
            return compact('conteoCitas', 'conteoActivas', 'conteoFinalizadas', 'conteoCanceladas');
        }catch(Exception $e){
            throw $e;
        }
        
    }

/**
 * Obtiene toda la información general para el dashboard del usuario recepcion:
 *  - Datos del usuario y su clínica.
 *  - Conteo de citas (activas, finalizadas, canceladas).
 *  - Progreso dentro de la guía interactiva.
 *
 * Combina información de múltiples servicios en una sola respuesta para ser consumida
 * desde el proyecto principal mediante API.
 *
 * @param  int  $usuario_id   ID del usuario autenticado.
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 */
    public function index(int $usuario_id){

        try{
            //Obtener la información detallada del usuario:
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            // Obtener la información relacionada a la guía de usuario:
            $datosGuia = $this->usuarioService->obtenerDatosGuia($usuario_id);

            //Obtener conteos relacionados a las citas del usuario:
            $conteoDatos=$this->conteoDatos($usuario_id);

            //Retorna la respuesta en formato JSON con los datos recopilados.
            return response()->json([
                'succes'=>true,
                'data'=>array_merge(
                    $datos,
                    $datosGuia,
                    $conteoDatos
                )
            ]);
        }catch(\Throwable $e){
            // Capturar cualquier error y retornar respuesta con detalles del error
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }

    //Calendario se encuentra en adminController-adminClinica/Calendario

    //Citas se encuentra en adminController-adminClinica/Citas

    //createcitas se encuentra en adminController-adminClinica/createcitas

    //detallecita se encuentra en adminController-adminPanel/DetalleCita

    //perfilrecepcion se encuentra en adminController-adminPanel/DetalleUsuario

   
}

<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use App\Services\UsuarioService;
use App\Models\Pacientes;
use App\Models\Familiar_paciente;
use App\Models\Personal;
use App\Models\Servicio;
use App\Models\Especialidad;
use App\Models\Ciudades;
use App\Models\Status;
use App\Models\Puesto;
use App\Models\Citas;
use App\Models\Observaciones;
use App\Models\Expedientes;
use App\Models\PasoGuia;
use App\Models\ProgresoUsuarioGuia;
use Carbon\Carbon;


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
    public function conteoDatos($usuario_id){
        
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
    public function index($usuario_id){

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

/**
 * Obtiene los datos necesarios (pacientes activos, personal médico y servicios)
 * para la vista de creación de una nueva cita, filtrados por la clínica especificada.
 *
 * @param int $clinica_id El ID de la clínica para filtrar los datos.
 * @return \Illuminate\Http\JsonResponse Retorna una respuesta JSON con 'success' y los datos
 * (pacientes, personal, servicios) en caso de éxito,
 * o un mensaje de error con código 500 en caso de fallo.
 */
    public function index_createcita($clinica_id){

        try{
            //Obtener todos los pacientes activos (status_id = 1) de la clínica específica.
            $pacientes=Pacientes::where('status_id','=',1)
            ->where('clinica_id',$clinica_id)->get();
            
            // Obtener el personal que cumple con:
            //    a) Está asignado a un usuario con clinica_id y status_id = 1.
            //    b) Tiene un puesto_id = 2 (medico).
            $personal=Personal::whereHas('usuario',function($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id)
                    ->where('status_id',1);
            })->where('puesto_id',2)->get();

            //Obtener todos los servicios ofrecidos por la clínica específica.
            $servicios=Servicio::where('clinica_id',$clinica_id)->get();

            //Retorna la respuesta en formato JSON con los datos recopilados.
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'pacientes',
                    'personal',
                    'servicios'
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
    
/**
 * Obtiene y formatea todas las citas programadas para la clínica asociada al usuario dado.
 *
 * Utiliza el ID de usuario para obtener la clínica y luego recupera todas las citas
 * de esa clínica, incluyendo la información relacionada (personal, paciente, servicio).
 * Los datos de las citas son mapeados para un formato específico de calendario/vista.
 *
 * @param int $usuario_id El ID del usuario actual para determinar la clínica.
 * @return \Illuminate\Http\JsonResponse Retorna una respuesta JSON con 'success' y
 * el array de citas formateadas ('citas') en caso de éxito,
 * o un mensaje de error con código 500 en caso de fallo.
 */    
    public function index_calendario($usuario_id){

        try{
            //Obtener datos clave del usuario
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            //Obtiene todas las citas relacionadas al usuario que pertenece a una clínica.
            $citas=Citas::with(['personal','paciente','servicio'])->wherehas('personal.usuario',function($q) use($datos){
                    $q->where('clinica_id',$datos['clinica_id']);
                })
                ->get()
                ->map(function ($cita){
                    return[
                        'id' => $cita->id,
                        'fecha_citaFormato'=>Carbon::parse($cita->fecha_cita)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                        'fecha_cita' => $cita->fecha_cita,  
                        'hora_inicio' => $cita->hora_inicio,
                        'hora_fin' => $cita->hora_fin,
                        'paciente_id'=>$cita->paciente->id,
                        'alias'=>$cita->paciente->alias,
                        'nombre_paciente' => $cita->paciente->nombre,
                        'apellidoP_paciente' => $cita->paciente->apellido_paterno,
                        'apellidoM_paciente' => $cita->paciente->apellido_materno,
                        'nombre_medico' => $cita->personal->nombre,
                        'apellidoP_medico' => $cita->personal->apellido_paterno,
                        'apellidoM_medico' => $cita->personal->apellido_materno,
                        'servicio' => $cita->servicio->descripcion,
                        'status' => $cita->status->descripcion
                    ];
                });

            //Retorna la respuesta en formato JSON con los datos recopilados.
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'citas'
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

/**
 * Obtiene un listado de citas programadas para la clínica del usuario,
 * permitiendo filtrar los resultados por paciente, médico, estado y fecha.
 *
 * Además, retorna los listados completos de personal médico y pacientes
 * de la clínica para ser usados en los selectores de filtrado en la vista.
 *
 * @param \Illuminate\Http\Request $request La solicitud HTTP que puede contener parámetros de filtro (paciente, medico, estado, fecha).
 * @param int $usuario_id El ID del usuario actual para determinar la clínica.
 * @return \Illuminate\Http\JsonResponse Retorna una respuesta JSON con 'success' y los datos
 * (personal_medico, pacientes, citas, resultado del filtro) en caso de éxito,
 * o un mensaje de error con código 500 en caso de fallo.
 */
    public function index_citas(Request $request,$usuario_id){

        try{
            //Obtener datos clave del usuario
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            //Obtener listado de médicos de la clínica.
            $personal_medico=Personal::whereHas('usuario',function($q) use($datos){
                $q->where('clinica_id',$datos['clinica_id']);
            })->where('puesto_id',2)->get();

            //Obtener listado de pacientes de la clínica para los filtros.
            $pacientes=pacientes::where('clinica_id',$datos['clinica_id'])->get();
            
            //Iniciar la consulta base para obtener todas las citas de la clínica.
            $query=citas::query()->with(['paciente','servicio','personal.usuario','status'])
            ->whereHas('personal.usuario',function($q) use($datos){
                $q->where('clinica_id',$datos['clinica_id']);
            })->orderBy('fecha_cita','desc')
            ->orderBy('hora_inicio','asc');  

            // Array para almacenar los filtros aplicados y mostrarlos al usuario.
            $filtrobusqueda=[];

            // --- Aplicación de Filtros Condicionales ---
            if($request->filled('paciente')){
                $query->whereHas('paciente',function($q) use ($request){
                    $q->where('id','=',$request->paciente);
                });
                $paciente=Pacientes::find($request->paciente);
                $filtrobusqueda[]="Paciente: ".($paciente ? $paciente->nombre:"Desconocido");
            }

            if($request->filled('medico')){
                $query->whereHas('personal',function($q) use ($request){
                    $q->where('id','=',$request->medico);
                });
                $medico=Personal::find($request->medico);
                $filtrobusqueda[] = "Medico: ".($medico ? $medico->nombre:"Desconocido");
            }

            if ($request->filled('estado')) {
                $query->where('status_id', $request->estado);
                $filtrobusqueda[]="Estado: ".ucfirst($request->estado);
            }

            if ($request->filled('fecha')) {
                $query->whereDate('fecha_cita', $request->fecha);
                $filtrobusqueda[]="Fecha: ".$request->fecha;
            }

            //Ejecutar la consulta final y obtener las citas.
            $citas = $query->get();

            //Preparar el mensaje de resultado del filtro.
            $resultado=count($citas)>0 ? "Se encontraron: ".count($citas)." citas":"No se encontraron resultado";
            if(!empty($filtrobusqueda)){
                $resultado.=" para ".implode(", ",$filtrobusqueda);
            }

            //Retorna la respuesta en formato JSON con los datos recopilados.
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'personal_medico',
                    'pacientes',
                    'citas',
                    'resultado'
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

    //detallecita se encuentra en adminController-adminPanel/DetalleCita

    //perfilrecepcion se encuentra en adminController-adminPanel/DetalleUsuario

   
}

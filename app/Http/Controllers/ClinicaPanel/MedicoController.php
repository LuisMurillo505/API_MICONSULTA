<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Citas;
use App\Models\Pacientes;
use App\Services\UsuarioService;
use App\Services\NotificacionService;
use App\Services\PlanService;

use Illuminate\Http\Request;

class MedicoController extends Controller
{
    protected $usuarioService;
    protected $planService;
    protected $notificacionService;
    protected $apiService;

    public function __construct( 
        UsuarioService $usuarioServices,PlanService $planService,NotificacionService $notificacionService){
        $this->usuarioService=$usuarioServices; 
        $this->planService=$planService;
        $this->notificacionService=$notificacionService;
    }  

/**
 * Obtiene el conteo de citas asociadas al usuario dentro de su clínica,
 * clasificadas por su estado actual.
 *
 * Este método utiliza los datos generales del usuario —incluyendo su clínica
 * y su personal asociado— para filtrar todas las citas que correspondan a:
 *  - La misma clínica.
 *  - El mismo personal registrado para ese usuario.
 *
 * @param  int  $usuario_id    ID del usuario cuyo conteo de citas se desea obtener.
 * @return array              
 *
 * @throws \Throwable          Lanza excepción si ocurre un error dentro del servicio `DatosUsuario`
 *                             o durante las consultas de conteo.
 */
    public function conteoDatos($usuario_id){

        $datos=$this->usuarioService->DatosUsuario($usuario_id);

        $conteoCitas=citas::whereHas('personal.usuario',function($q) use($datos){
            $q->where('clinica_id',$datos['clinica_id']);
            $q->where('personal_id',$datos['personal_id']);
        })->count();

        $conteoActivas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
            $q->where('personal_id', $datos['personal_id']);
        })->where('status_id', 1)->count();

        $conteoFinalizadas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
            $q->where('personal_id', $datos['personal_id']);
        })->where('status_id', 3)->count();

        $conteoCanceladas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
            $q->where('personal_id', $datos['personal_id']);
        })->where('status_id', 4)->count();

        return compact('conteoCitas','conteoActivas','conteoFinalizadas','conteoCanceladas');
    }

/**
 * Obtiene toda la información general para el dashboard del usuario medico:
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

            //Obtener conteos relacionados a las citas del usuario:
            $conteoDatos=$this->conteoDatos($usuario_id);

            // Obtener la información relacionada a la guía de usuario:
            $datosGuia = $this->usuarioService->obtenerDatosGuia($usuario_id);

            //Retorna la respuesta en formato JSON con los datos recopilados.
            return response()->json([
                'success' => true,
                'data'=>array_merge(
                    $datos,
                    $datosGuia,
                    $conteoDatos,
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
 * Obtiene el listado de citas del usuario medico junto con:
 *  - Lista de pacientes con citas registradas.
 *  - Filtros aplicados (paciente, estado, fecha).
 *  - Resultado en forma de mensaje descriptivo.
 *  - Envío automático de notificaciones pendientes.
 *
 * Esta función es usada en el panel del profesional para consultar
 * y filtrar sus citas programadas.
 *
 * @param  \Illuminate\Http\Request  $request     Parámetros de búsqueda (paciente, estado, fecha).
 * @param  int                       $usuario_id  ID del usuario autenticado.
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 */
     public function index_citas(Request $request,$usuario_id){
         
        try{
            //Obtener datos generales del usuario,
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            // Enviar notificaciones pendientes de citas
            $this->notificacionService->notificar_cita($datos['personal_id']);

            //Obtener lista de pacientes que tienen al menos una cita con este usuario:
            $pacientes=Citas::with(['paciente'])
            ->whereHas('personal.usuario',function($q) use($datos){
                $q->where('id',$datos['usuario_id'])
                ->where('clinica_id',$datos['clinica_id']);
            })->select('paciente_id')   
            ->distinct()
            ->get()
            ->pluck('paciente')
            ->unique('id')
            ->sortBy('nombre')
            ->values();    

             /**
             * Consulta principal de citas del usuario.
             * Incluye relaciones necesarias para mostrar toda la información en pantalla.
             */
            $query=Citas::query()->with(['paciente','servicio','personal.usuario','status'])
                ->whereHas('personal.usuario',function($q) use($datos){
                    $q->where('id',$datos['usuario_id']);
                })->orderBy('fecha_cita','desc')
                ->orderBy('hora_inicio','asc');

            //Lista que describe los filtros aplicados por el usuario,
            $filtrobusqueda=[];

            // Filtro por paciente
            if($request->filled('paciente')){
                $query->whereHas('paciente',function($q) use ($request){
                    $q->where('id','=',$request->paciente)->select('nombre');
                });
                $paciente=Pacientes::find($request->paciente);
                $filtrobusqueda[]="Paciente: ".($paciente ? $paciente->nombre:"Desconocido");
            }

            // Filtro por estado
            if ($request->filled('estado')) {
                $query->where('status_id', $request->estado);
                $filtrobusqueda[]="Estado: ".ucfirst($request->estado);
            }

            // Filtro por fecha
            if ($request->filled('fecha')) {
                $query->whereDate('fecha_cita', $request->fecha);
                $filtrobusqueda[]="Fecha: ".$request->fecha;
            }

            // Ejecutar consulta final
            $citas = $query->get();

            //Construir mensaje descriptivo del resultado obtenido.
            $resultado=count($citas)>0 ? "Se encontraron: ".count($citas)." citas":"No se encontraron resultado";
            if(!empty($filtrobusqueda)){
                $resultado.=" para ".implode(", ",$filtrobusqueda);
            }

            //Retornar los datos en formato JSON con estado de éxito.
            return response()->json([
                'success' => true,
                'data'=>compact(
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

/**
 * Obtiene todas las citas del calendario asociadas a un usuario medico.
 *
 * Esta función recupera las citas relacionadas al usuario (médico/personal)
 * perteneciente a cierta clínica, formatea los datos y los retorna en un JSON
 * para ser utilizados en un calendario o vista similar.
 *
 * @param int $usuario_id  ID del usuario autenticado.
 * @return \Illuminate\Http\JsonResponse
 */
    public function index_calendario($usuario_id){

        try{
            // Obtener datos del usuario, incluyendo usuario_id y clinica_id
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            //Obtiene todas las citas relacionadas al usuario que pertenece a una clínica.
            $citas=Citas::with(['personal.usuario','paciente','servicio'])
                ->whereHas('personal.usuario',function($query) use($datos){
                    $query->where('id','=',$datos['usuario_id'])
                    ->where('clinica_id',$datos['clinica_id']);
                })->get()
                // Transformar cada cita en un arreglo formateado
                ->map(function ($cita){
                    return[
                        'id' => $cita->id,
                        'fecha_citaFormato'=>Carbon::parse($cita->fecha_cita)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                        'fecha_cita' => $cita->fecha_cita,  
                        'hora_inicio' => $cita->hora_inicio,
                        'hora_fin' => $cita->hora_fin,
                        'paciente_id'=>$cita->paciente->id, 
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

            //Retornar los datos en formato JSON con estado de éxito.
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

    //perfilmedico se encuentra en adminController-adminPanel/DetalleUsuario

    //detallecita se encuentra en adminController-adminPanel/DetalleCita


}

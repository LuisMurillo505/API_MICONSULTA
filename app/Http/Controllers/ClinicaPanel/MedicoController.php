<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Citas;
use App\Models\Observaciones;
use App\Models\Pacientes;
use App\Models\Familiar_paciente;
use App\Models\Expedientes;
use App\Models\Especialidad;
use App\Models\Puesto;
use App\Services\UsuarioService;
use App\Models\Servicio;
use App\Models\Personal;
use App\Services\NotificacionService;
use App\Services\PlanService;
use App\Models\PasoGuia;
use App\Models\ProgresoUsuarioGuia;
use App\Models\Disponibilidad;

use Illuminate\Http\Request;

class MedicoController extends Controller
{
    protected $usuarioService;
    protected $planService;
    protected $notificacionService;
    protected $apiService;


     /**
     * Constructor que inyecta el servicio de notificaciones.
     */
    public function __construct( 
        UsuarioService $usuarioServices,PlanService $planService,NotificacionService $notificacionService){
        $this->usuarioService=$usuarioServices; 
        $this->planService=$planService;
        $this->notificacionService=$notificacionService;
    }  


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

    public function index($usuario_id){
         
        try{
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            $conteoDatos=$this->conteoDatos($usuario_id);

            $datosGuia = $this->usuarioService->obtenerDatosGuia($usuario_id);

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

     public function index_citas(Request $request,$usuario_id){
         
        try{
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            // Enviar notificaciones pendientes de citas
            $this->notificacionService->notificar_cita($datos['personal_id']);

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

            $query=Citas::query()->with(['paciente','servicio','personal.usuario','status'])
                ->whereHas('personal.usuario',function($q) use($datos){
                    $q->where('id',$datos['usuario_id']);
                })->orderBy('fecha_cita','desc')
                ->orderBy('hora_inicio','asc');

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

            $citas = $query->get();

            $resultado=count($citas)>0 ? "Se encontraron: ".count($citas)." citas":"No se encontraron resultado";
            if(!empty($filtrobusqueda)){
                $resultado.=" para ".implode(", ",$filtrobusqueda);
            }

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
     * Muestra la vista del calendario con todas las citas del médico.
     */

    public function index_calendario($usuario_id){

        try{
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            $citas=Citas::with(['personal.usuario','paciente','servicio'])
                ->whereHas('personal.usuario',function($query) use($datos){
                    $query->where('id','=',$datos['usuario_id'])
                    ->where('clinica_id',$datos['clinica_id']);
                })->get()
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

    // /**
    //  * Muestra la vista del perfil del médico con su especialidad y puesto.
    //  */
    // public function index_perfil($usuario_id){
   
    //     try{
    //         $datos=$this->usuarioService->DatosUsuario($usuario_id);

    //         $especialidad=Especialidad::where('clinica_id',$datos['clinica_id'])->get();
    //         $puesto=Puesto::all();
    //         $personal=Personal::where('usuario_id',$datos['usuario_id'])->first();
    //         $especialidad_user=Especialidad::where('id',$personal->especialidad_id)->first();
    //         $puesto_user=Puesto::where('id',$personal->puesto_id)->first();
    //         $google=$personal->usuario->google;
    //         $disponibilidad=Disponibilidad::where('personal_id',$personal->id)->get()->keyBy('dia');

    //         $googleCalendar=$this->planService->puedeUsarGoogleCalendar($datos['clinica_id']);

    //         return response()->json([
    //             'success'=>true,
    //             'data'=>compact(
    //                 'especialidad',
    //                 'puesto',
    //                 'personal',
    //                 'especialidad_user',
    //                 'puesto_user',
    //                 'google',
    //                 'disponibilidad',
    //                 'googleCalendar'
    //             )
    //         ]);

    //     }catch(\Throwable $e){
    //         // Capturar cualquier error y retornar respuesta con detalles del error
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Error al obtener datos',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
        
    // }

    //  /**
    //  * Busca citas del médico por paciente, estado o fecha.
    //  */
    //  public function buscarCitas(Request $request){

    //     $datos=$this->usuarioService->DatosUsuario();

    //     $conteoDatos=$this->conteoDatos();
    //     $datosGuia = $this->obtenerDatosGuia();


    //     $pacientes=Citas::with(['paciente'])
    //     ->whereHas('personal.usuario',function($q) use($datos){
    //         $q->where('id',$datos['usuario_id'])
    //         ->where('clinica_id',$datos['clinica_id']);
    //     })->select('paciente_id')
    //     ->distinct()
    //     ->get()
    //     ->pluck('paciente')
    //     ->unique('id')
    //     ->sortBy('nombre')
    //     ->values();

    //     $query=Citas::query()->orderBy('fecha_cita','desc');

    //     $query->where('personal_id',$datos['personal_id']);
    //     $query->with('paciente');

    //     $filtrobusqueda=[];

    //     // Filtro por paciente
    //     if($request->filled('paciente')){
    //         $query->whereHas('paciente',function($q) use ($request){
    //             $q->where('id','=',$request->paciente)->select('nombre');
    //         });
    //         $paciente=Pacientes::find($request->paciente);
    //         $filtrobusqueda[]="Paciente: ".($paciente ? $paciente->nombre:"Desconocido");
    //     }

    //     // Filtro por estado
    //     if ($request->filled('estado')) {
    //         $query->where('status_id', $request->estado);
    //         $filtrobusqueda[]="Estado: ".ucfirst($request->estado);

    //     }

    //      // Filtro por fecha
    //     if ($request->filled('fecha')) {
    //         $query->whereDate('fecha_cita', $request->fecha);
    //         $filtrobusqueda[]="Fecha: ".$request->fecha;
    //     }

    //     $citas = $query->get();

    //     $resultado=count($citas)>0 ? "Se encontraron: ".count($citas)." citas":"No se encontraron resultado";
    //     if(!empty($filtrobusqueda)){
    //         $resultado.=" para ".implode(", ",$filtrobusqueda);
    //     }

    //     // $conteoCitas=citas::whereHas('personal.usuario',function($q) use($datos){
    //     //     $q->where('clinica_id',$datos['clinica_id']);
    //     //     $q->where('personal_id',$datos['personal_id']);
    //     // })->count();

    //     // $conteoActivas = citas::whereHas('personal.usuario', function($q) use($datos) {
    //     //     $q->where('clinica_id', $datos['clinica_id']);
    //     //     $q->where('personal_id', $datos['personal_id']);
    //     // })->where('status_id', 1)->count();

    //     // $conteoFinalizadas = citas::whereHas('personal.usuario', function($q) use($datos) {
    //     //     $q->where('clinica_id', $datos['clinica_id']);
    //     //     $q->where('personal_id', $datos['personal_id']);
    //     // })->where('status_id', 3)->count();

    //     // $conteoCanceladas = citas::whereHas('personal.usuario', function($q) use($datos) {
    //     //     $q->where('clinica_id', $datos['clinica_id']);
    //     //     $q->where('personal_id', $datos['personal_id']);
    //     // })->where('status_id', 4)->count();

    //     return view('medico.medico', array_merge(compact('citas','pacientes','resultado'),$datos, $datosGuia,$conteoDatos));
    // }

}

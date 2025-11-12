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
    protected $apiService;


     /**
     * Constructor que inyecta el servicio de notificaciones.
     */
    public function __construct( 
        UsuarioService $usuarioServices,PlanService $planService){
        $this->usuarioService=$usuarioServices; 
        $this->planService=$planService;
    }  
    

     /**
     * Muestra la vista principal del médico con sus citas y pacientes asignados.
     * 
     * También dispara notificaciones sobre nuevas citas.
     */

      public function obtenerDatosGuia($usuario_id)
    {

       // Total de pasos en la guía (todos los pasos activos no existen, se asume que todos están activos)
        $total_pasos = PasoGuia::count();

        $total_pasosF = 0;
        if ($usuario_id) {
            $total_pasosF = ProgresoUsuarioGuia::where('usuario_id', $usuario_id)
                ->where('esta_completado', true)
                ->count();
        }
        $pasosT = PasoGuia::all()->count();
        $clave_paso = PasoGuia::where('id', 1)->value('clave_paso');
        $paso_completo = [];
        $paso_completo = ProgresoUsuarioGuia::where('usuario_id', $usuario_id)->where('esta_completado', true)->pluck('clave_paso');
        // $PasoGuia2 = PasoGuia::with(['progreso' => function ($q) use ($usuario_id) {
        //     $q->where('usuario_id', $usuario_id);
        // }])->get();
        $PasoGuia2 = PasoGuia::whereHas('progreso', function ($q) use ($usuario_id) {
            $q->where('usuario_id', $usuario_id);
        })->with(['progreso' => function ($q) use ($usuario_id) {
            $q->where('usuario_id', $usuario_id);
        }])->get();

        return compact('total_pasos', 'total_pasosF', 'pasosT', 'clave_paso', 'paso_completo', 'PasoGuia2');
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

            $datosGuia = $this->obtenerDatosGuia($usuario_id);

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
     * Muestra la vista del calendario con todas las citas del médico.
     */

    // public function index_calendario(){

    //     $datos=$this->usuarioService->DatosUsuario();

    //     $datosGuia = $this->obtenerDatosGuia();

    //     $citas=Citas::with(['personal.usuario','paciente','servicio'])
    //                 ->whereHas('personal.usuario',function($query) use($datos){
    //                     $query->where('id','=',$datos['usuario_id'])
    //                     ->where('clinica_id',$datos['clinica_id']);
    //                 })->get()
    //                 ->map(function ($cita){
    //                     return[
    //                         'id' => $cita->id,
    //                         'fecha_citaFormato'=>Carbon::parse($cita->fecha_cita)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
    //                         'fecha_cita' => $cita->fecha_cita,  
    //                         'hora_inicio' => $cita->hora_inicio,
    //                         'hora_fin' => $cita->hora_fin,
    //                         'paciente_id'=>$cita->paciente->id, 
    //                         'nombre_paciente' => $cita->paciente->nombre,
    //                         'apellidoP_paciente' => $cita->paciente->apellido_paterno,
    //                         'apellidoM_paciente' => $cita->paciente->apellido_materno,
    //                         'nombre_medico' => $cita->personal->nombre,
    //                         'apellidoP_medico' => $cita->personal->apellido_paterno,
    //                         'apellidoM_medico' => $cita->personal->apellido_materno,
    //                         'servicio' => $cita->servicio->descripcion,
    //                         'status' => $cita->status->descripcion
    //                     ];
    //                 });
        
    //     return view('medico.calendariomedico', array_merge(compact('citas'),$datos, $datosGuia));
    // }

    //  /**
    //  * Muestra los detalles de una cita específica, incluyendo paciente, familiar, expediente y observaciones.
    //  */
    //  public function index_detalleCita($cita_id,$paciente_id){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $cita=Citas::where('id',$cita_id)->first();
    //     $paciente=Pacientes::where('id',$paciente_id)->first();
    //     $familiar_paciente=familiar_paciente::where('paciente_id',$paciente_id)->first();
    //     $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();
    //     $servicio=Servicio::where('id',$cita->servicio_id)->first();
    //     $expediente=Expedientes::where('cita_id',$cita_id)->get();

    //     return view('medico.detallecita',array_merge(compact('cita','observaciones','paciente','familiar_paciente','expediente','servicio'),$datos, $datosGuia));
    // }

    // /**
    //  * Muestra la vista del perfil del médico con su especialidad y puesto.
    //  */
    // public function index_perfil(){
   
    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $especialidad=Especialidad::where('clinica_id',$datos['clinica_id'])->get();
    //     $puesto=Puesto::all();
    //     $personal=Personal::where('usuario_id',$datos['usuario_id'])->first();
    //     $especialidad_user=Especialidad::where('id',$personal->especialidad_id)->first();
    //     $puesto_user=Puesto::where('id',$personal->puesto_id)->first();
    //     $google=$personal->usuario->google;
    //     $disponibilidad=Disponibilidad::where('personal_id',$personal->id)->get()->keyBy('dia');

    //     $googleCalendar=$this->planService->puedeUsarGoogleCalendar($datos['clinica_id']);

    //     return view('medico.perfilmedico', array_merge(compact('especialidad','puesto','personal','especialidad_user','puesto_user','google', 'disponibilidad','googleCalendar'),$datos, $datosGuia));
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

    //  public function aviso_privacidad(){
    //     $datos=$this->usuarioService->DatosUsuario();

    //     return view('medico.AvisoPrivacidad',$datos);
    // }
}

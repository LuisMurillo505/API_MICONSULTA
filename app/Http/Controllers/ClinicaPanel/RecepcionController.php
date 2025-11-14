<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
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
     * Obtiene datos del usuario autenticado y su clínica.
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
        
        $datos=$this->usuarioService->datosUsuario($usuario_id);

        $conteoCitas=citas::whereHas('personal.usuario',function($q) use($datos){
            $q->where('clinica_id',$datos['clinica_id']);
        })->count();

        $conteoActivas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 1)->count();

        $conteoFinalizadas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 3)->count();

        $conteoCanceladas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 4)->count();

        return compact('conteoCitas', 'conteoActivas', 'conteoFinalizadas', 'conteoCanceladas');
    }

    /**
     * Muestra la vista principal de recepción con pacientes, servicios y personal.
     */
    public function index($usuario_id){

        try{
        $datos=$this->usuarioService->DatosUsuario($usuario_id);

        $datosGuia = $this->usuarioService->obtenerDatosGuia($usuario_id);

        
        return response()->json([
            'succes'=>true,
            'data'=>array_merge(
                $datos,
                $datosGuia
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
     * Muestra formulario para crear paciente desde recepción.
     */
    // public function index_createpaciente(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $estado=Status::limit('2')->get();
    //     $ciudades=Ciudades::all();
        
    //     return view('recepcion.createpacientesRec',compact('estado','ciudades'),$datos, $datosGuia);
    // }

    // /**
    //  * Muestra el perfil del usuario de recepción.
    //  */
    // public function index_perfil(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $especialidad=Especialidad::where('clinica_id',$datos['clinica_id'])->get();
    //     $puesto=Puesto::all();
    //     $personal=Personal::where('usuario_id',$datos['usuario_id'])->first();
    //     $especialidad_user=Especialidad::where('id',$personal->especialidad_id)->first();
    //     $puesto_user=Puesto::where('id',$personal->puesto_id)->first();

    //     return view('recepcion.perfilrecepcion', array_merge(compact('especialidad','puesto','personal','especialidad_user','puesto_user'),$datos, $datosGuia));
    // }

    
    // /**
    //  * Muestra el calendario con las citas de la clínica.
    //  */
    // public function index_calendario(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $citas=Citas::with(['personal','paciente','servicio'])->wherehas('personal.usuario',function($q) use($datos){
    //                     $q->where('clinica_id',$datos['clinica_id']);
    //                 })
    //                 ->get()
    //                 ->map(function ($cita){
    //                     return[
    //                         'id' => $cita->id,
    //                         'fecha_citaFormato'=>Carbon::parse($cita->fecha_cita)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
    //                         'fecha_cita' => $cita->fecha_cita,  
    //                         'hora_inicio' => $cita->hora_inicio,
    //                         'hora_fin' => $cita->hora_fin,
    //                         'paciente_id'=>$cita->paciente->id,
    //                         'alias'=>$cita->paciente->alias,
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
        
    //     return view('recepcion.calendario', array_merge(compact('citas'),$datos, $datosGuia));
    // }

    // /**
    //  * Muestra lista de citas con filtros para médico y paciente.
    //  */

    // public function index_citas(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $conteoDatos=$this->conteoDatos();

    //     $personal_medico=Personal::whereHas('usuario',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->where('puesto_id',2)->get();

    //     $pacientes=pacientes::where('clinica_id',$datos['clinica_id'])->get();
        
    //      $citas=citas::whereHas('personal.usuario',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->orderBy('fecha_cita','desc')
    //     ->orderBy('hora_inicio','asc')->get();  
        
    //     $resultado=count($citas)>0 ? "Se encontraron: ".count($citas). " citas" : "No se encontraron resultados"; 

       

    //     return view('recepcion.citarecepcion',array_merge(compact('citas','personal_medico','pacientes','resultado'),$datos,$conteoDatos, $datosGuia));
    // }

    // /**
    //  * Muestra detalles de una cita y su paciente.
    //  */
    // public function index_detallecita($cita_id,$paciente_id){
       
    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    
    //     $cita=Citas::where('id',$cita_id)->first();
    //     $paciente=Pacientes::where('id',$paciente_id)->first();
    //     $familiar_paciente=Familiar_paciente::where('paciente_id',$paciente_id)->first();
    //     $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();
    //     $servicio=Servicio::where('id',$cita->servicio_id)->first();
    //     $medico=Personal::where('id','=',$cita->personal_id)->first();
    //     $expediente=Expedientes::where('cita_id',$cita_id)->get();


    //     return view('recepcion.detallecitarecepcion',array_merge(compact('cita','observaciones','paciente','familiar_paciente','expediente','servicio','medico'),$datos, $datosGuia));

    // }

    // /**
    //  * Busca citas según filtros enviados por request.
    //  */
    // public function buscarCitas(Request $request){

    //     $datos=$this->usuarioService->DatosUsuario();

    //     $conteoDatos=$this->conteoDatos();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $personal_medico=Personal::Where('puesto_id','=',2)->get();
    //     $pacientes=pacientes::all();

    //     $query=Citas::query()->orderBy('fecha_cita','desc');

    //     $filtrobusqueda=[];

    //     if($request->filled('paciente')){
    //         $query->whereHas('paciente',function($q) use ($request){
    //             $q->where('id','=',$request->paciente);
    //         });
    //         $paciente=Pacientes::find($request->paciente);
    //         $filtrobusqueda[]="Paciente: ".($paciente ? $paciente->nombre:"Desconocido");
    //     }

    //     if($request->filled('medico')){
    //         $query->whereHas('personal',function($q) use ($request){
    //             $q->where('id','=',$request->medico);
    //         });
    //         $medico=Personal::find($request->medico);
    //         $filtrobusqueda[] = "Medico: ".($medico ? $medico->nombre:"Desconocido");
    //     }

    //     if ($request->filled('estado')) {
    //         $query->where('status_id', $request->estado);
    //         $filtrobusqueda[]="Estado: ".ucfirst($request->estado);

    //     }

    //     if ($request->filled('fecha')) {
    //         $query->whereDate('fecha_cita', $request->fecha);
    //         $filtrobusqueda[]="Fecha: ".$request->fecha;

    //     }

    //     $citas = $query->get();

    //     $resultado=count($citas)>0 ? "Se encontraron: ".count($citas)." citas":"No se encontraron resultado";
    //     if(!empty($filtrobusqueda)){
    //         $resultado.=" para ".implode(", ",$filtrobusqueda);
    //     }
    

    //     return view('recepcion.citarecepcion', array_merge(compact('citas','personal_medico','pacientes','resultado'),$datos,$conteoDatos, $datosGuia));
    // }

    // public function aviso_privacidad(){
    //     $datos=$this->usuarioService->DatosUsuario();

    //     return view('recepcion.AvisoPrivacidad',$datos);
    // }

   
}

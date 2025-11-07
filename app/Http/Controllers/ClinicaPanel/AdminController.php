<?php

namespace App\Http\Controllers\ClinicaPanel;
 
use App\Http\Controllers\Controller;
use App\Services\UsuarioService;
use App\Services\CitaService;
use App\Models\Especialidad;
use App\Models\Expedientes;
use App\Models\Observaciones;
use App\Models\Servicio;
use App\Models\Puesto;  
use App\Models\Usuario;
use App\Models\Personal;
use App\Models\ArchivosPaciente;
use App\Models\Pacientes;
use App\Models\Disponibilidad;
use App\Models\Familiar_paciente;
use App\Models\Status;
use App\Models\Citas;
use App\Models\Ciudades;
use App\Services\PlanService;
use App\Models\Clinicas;
use App\Models\PasoGuia;
use App\Models\Somatometria_Paciente;
use App\Models\ProgresoUsuarioGuia;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Controlador AdminController
 * Responsable de gestionar las vistas y operaciones relacionadas con la administración
 * de la clínica: usuarios, pacientes, citas, expedientes y catálogos.
 */
class AdminController extends Controller 
{
    protected $usuarioService;
    protected $citaService;
    protected $planService;
    
    public function __construct(UsuarioService $usuarioServices, PlanService $planServices)
    {
        $this->usuarioService = $usuarioServices;
        // $this->citaService = $citaServices;
        $this->planService = $planServices;
    }

    public function conteoDatos($usuario_id)
    {
        $datos=$this->usuarioService->DatosUsuario($usuario_id);

       $conteoUsuarios=Personal::whereHas('usuario',function($q) use($datos){
            $q->where('clinica_id',$datos['clinica_id'])
                ->where('status_id',1);
        })->count();

        $conteoServicios=Servicio::where('clinica_id',$datos['clinica_id'])
        ->where('status_id',1)->count();

        //conteo de apartados
        $servicios=Servicio::where('clinica_id',$datos['clinica_id'])->get(); 

        $conteoPacientes=pacientes::where('clinica_id',$datos['clinica_id'])->count();

        $conteoCitas=citas::whereHas('personal.usuario',function($q) use($datos){
            $q->where('clinica_id',$datos['clinica_id']);
        })->count();

        $conteoCitasHoy=citas::whereHas('personal.usuario',function($q) use($datos){
            $q->where('clinica_id',$datos['clinica_id']);
        })->whereDate('fecha_cita', Carbon::today())->count();

        $conteoPacientesHoy=pacientes::where('clinica_id',$datos['clinica_id'])->whereDate('created_at', Carbon::today())->count();

        $conteoActivas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 1)->count();

        $conteoFinalizadas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 3)->count();

        $conteoCanceladas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 4)->count();

        //usuarios permitidos
        $usuariosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
            $query->where('funcion_id', 2);
        }])->where('id',$datos['clinica_id'])
        ->whereHas('suscripcion.plan.funciones_planes',function($q) {
            $q->where('funcion_id',2);
        })->first();
        

         if($usuariosPermitidos->suscripcion->plan->funciones_planes->cantidad > $conteoUsuarios){
            $conteoUsuariosP= $usuariosPermitidos->suscripcion->plan->funciones_planes->cantidad - $conteoUsuarios;

        }else{
            $conteoUsuariosP = null;
        }

        //servicios permitidos
         $serviciosPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
            $query->where('funcion_id', 1);
        }])->where('id',$datos['clinica_id'])
        ->whereHas('suscripcion.plan.funciones_planes',function($q) {
            $q->where('funcion_id',1);
        })->first();


        if($serviciosPermitidos->suscripcion->plan->funciones_planes->cantidad > $conteoServicios){
            $conteoServiciosP= $serviciosPermitidos->suscripcion->plan->funciones_planes->cantidad - $conteoServicios;

        }else{
            $conteoServiciosP = null;
        }

        //pacientes permitidos
         $pacientesPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
            $query->where('funcion_id', 3);
        }])->where('id',$datos['clinica_id'])
        ->whereHas('suscripcion.plan.funciones_planes',function($q) {
            $q->where('funcion_id',3);
        })->first();

        if($pacientesPermitidos->suscripcion->plan->funciones_planes->cantidad == null){
            $conteoPacientesP = 'Ilimitado';
        }elseif($pacientesPermitidos->suscripcion->plan->funciones_planes->cantidad > $conteoPacientes){
            $conteoPacientesP= $pacientesPermitidos->suscripcion->plan->funciones_planes->cantidad - $conteoPacientes;
        }else{
            $conteoPacientesP = null;
        }

        //citas permitidos
         $citasPermitidos=Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
            $query->where('funcion_id', 4);
        }])->where('id',$datos['clinica_id'])
        ->whereHas('suscripcion.plan.funciones_planes',function($q) {
            $q->where('funcion_id',4);
        })->first();

        if($citasPermitidos->suscripcion->plan->funciones_planes->cantidad == null){
            $conteoCitasP = 'Ilimitado';
        }elseif($citasPermitidos->suscripcion->plan->funciones_planes->cantidad > $conteoCitas){
            $conteoCitasP= $citasPermitidos->suscripcion->plan->funciones_planes->cantidad - $conteoCitas;
        }else{
            $conteoCitasP = null;
        }

        return compact('conteoUsuarios', 'conteoServicios', 'servicios','conteoPacientes', 'conteoCitas', 'conteoCitasHoy', 'conteoPacientesHoy', 'conteoActivas', 'conteoFinalizadas', 'conteoCanceladas', 'conteoUsuariosP', 'conteoCitasP', 'conteoServiciosP', 'conteoPacientesP');
    }

    /**
     * Muestra la vista principal del panel de administración con conteos básicos.
     */
    public function index($usuario_id)
    {   
        try{
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            $datosGuia = $this->usuarioService->obtenerDatosGuia($usuario_id);

            $conteoDatos = $this->conteoDatos($usuario_id);

            return response()->json([
                'success' => true,
                'data'=>array_merge(
                    (array)$datos,
                    (array)$datosGuia,
                    (array)$conteoDatos)
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
     * Muestra la vista del perfil del la clinica
     */

    //   public function index_perfil(){
   
    //     $datos=$this->usuarioService->DatosUsuario();
    //     $usuario=Usuario::find($datos['usuario_id']);
    //     $ciudades=Ciudades::all();
    //     $google=$usuario->google ?? null;
    //     $googleCalendar=$this->planService->puedeUsarGoogleCalendar($datos['clinica_id']);

    //     $datosGuia = $this->obtenerDatosGuia();

    //     return view('admin.perfiladmin', array_merge(compact('ciudades','google','googleCalendar'),$datos, $datosGuia));
    // }

    // /**
    //  * Muestra el formulario para registrar un nuevo usuario.
    //  */
    // public function index_registro()  {

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     session(['previous_url' => url()->previous()]);

    //     $especialidad=Especialidad::where('status_id',1)
    //         ->where('clinica_id',$datos['clinica_id'])->get();
    //     $puesto=Puesto::all();

    //     return view('admin.createusuarios',array_merge(compact('especialidad','puesto'),$datos, $datosGuia));
    // }

    // /**
    //  * Muestra la lista de usuarios del personal médico/recepcion.
    //  */
    // public function index_usuarios()
    // {   
    //     $datos=$this->usuarioService->DatosUsuario();

    //     $datosGuia = $this->obtenerDatosGuia();

    //     $conteoDatos = $this->conteoDatos();

    //     $usuariosC=Personal::with(['especialidad', 'usuario'])
    //         ->whereHas('usuario', function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->get();

    //     $Lista_usuarios=Personal::whereHas('usuario',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->get();

    //     return view('admin.usuarios', array_merge(compact('usuariosC','Lista_usuarios'),$datos, $datosGuia, $conteoDatos));
    // }

    // /**
    //  * Permite buscar usuarios filtrando por estado o id.
    //  */
    // public function buscarUsuarios(Request $request){
        
    //     $datos=$this->usuarioService->DatosUsuario();

    //     $datosGuia = $this->obtenerDatosGuia();

    //     $conteoDatos = $this->conteoDatos();

    //     $Lista_usuarios=Personal::whereHas('usuario',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->get();


    //     $query=Personal::query()->with(['usuario'])->whereHas('usuario',function($q) use($datos){
    //             $q->where('clinica_id',$datos['clinica_id']);
    //         });

    //     if($request->filled('usuarios')){
    //         $query->where('id',$request->usuarios);
    //     }

    //     if($request->filled('estado')){
    //         $query->whereHas('usuario', function($q) use($request) {
    //             $q->where('status_id', $request->estado);
    //         });
    //     }

    //     $usuariosC = $query->get();  

    //     return view('admin.usuarios', array_merge(compact('usuariosC','Lista_usuarios','usuariosC'),$datos, $datosGuia, $conteoDatos));
    // }

    //  /**
    //  * Muestra el detalle de un usuario en particular.
    //  */
    // public function index_editusuarios($id){

    //     $datos=$this->usuarioService->DatosUsuario();

    //     $datosGuia = $this->obtenerDatosGuia();

    //     $usuarioP=Usuario::find($id);
       
    //     $especialidad=Especialidad::where('clinica_id',$datos['clinica_id'])->get();
    //     $puesto=Puesto::all();
    //     $personal=Personal::where('usuario_id',$id)->first();
    //     $especialidad_user=Especialidad::where('id',$personal->especialidad_id)->first();
    //     $puesto_user=Puesto::where('id',$personal->puesto_id)->first();
    //     $personal_estado=Usuario::where('id', $usuarioP->estado);
    //     $disponibilidad=Disponibilidad::where('personal_id',$personal->id)->get()->keyBy('dia');

    //     return view('admin.detalleusuarios', array_merge(compact('usuarioP','especialidad','puesto','personal','especialidad_user','puesto_user', 'personal_estado','disponibilidad'),$datos, $datosGuia));
    // }

    // /**
    //  * Muestra la lista de pacientes y actualiza sus edades.
    //  */
    // public function index_pacientes(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    //     $conteoDatos = $this->conteoDatos();

    //     $pacientes=Pacientes::whereHas('status',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->get();

    //     $Lista_paciente=Pacientes::where('clinica_id',$datos['clinica_id'])->get();

    //     $this->actualizarEdad();

    //     return view('admin.pacientes',array_merge(compact('pacientes','Lista_paciente'),$datos, $datosGuia, $conteoDatos));
    // }

    // /**
    //  * Búsqueda de pacientes por ID o estado.
    //  */
    // public function buscarPaciente(Request $request){
        
    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    //     $conteoDatos = $this->conteoDatos();

    //     $Lista_paciente=Pacientes::all()->where('clinica_id', $datos['clinica_id']);

    //     $query=Pacientes::query();

    //     $query = Pacientes::where('clinica_id', $datos['clinica_id']);

    //     if($request->filled('paciente')){
    //         $query->where('id',$request->paciente);
    //     }

    //     if($request->filled('estado')){
    //         $query->whereHas('status',function($q) use($request){
    //             $q->where('descripcion',$request->estado);
    //         });
    //     }
        
    //    // Filtrar por nombre (autocomplete)
    //     if ($request->ajax() && $request->filled('nombre')) {
    //         $pacientes = $query->where('nombre', 'like', $request->nombre . '%')
    //             ->limit(10)
    //             ->get(['id', 'nombre', 'foto', 'clinica_id']);

    //         // Modificar la foto y agregar nombre clínica usando $datos['nombre_clinica']
    //         foreach ($pacientes as $p) {
    //             $p->foto = $p->foto
    //                 ? asset('storage/' . $datos['nombre_clinica'] . '/pacientes/' . $p->foto)
    //                 : asset('images/p1.webp');

    //             // Opcional: agregar el nombre de la clínica en la respuesta
    //             $p->nombre_clinica = $datos['nombre_clinica'];
    //         }

    //         return response()->json($pacientes);
    //     }
    //     $pacientes = $query->get(); 

    //     return view('admin.pacientes', array_merge(compact('pacientes','Lista_paciente'),$datos, $datosGuia, $conteoDatos));
    // }

    //  /**
    //  * Actualiza la edad de todos los pacientes según su fecha de nacimiento.
    //  */
    // public function actualizarEdad(){
    //     $datos=$this->usuarioService->DatosUsuario();

    //     $pacientes=Pacientes::where('clinica_id',$datos['clinica_id'])->get();

    //     foreach($pacientes as $pac){
    //         $edad=Carbon::Parse($pac->fecha_nacimiento)->age;
    //         $pac->update([
    //             'edad'=>$edad
    //         ]);
    //     }
        
    // }

    //  /**
    //  * Vista de formulario para crear un nuevo paciente.
    //  */
    // public function index_createpaciente(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     session(['previous_url' => url()->previous()]);

    //     $estado=Status::limit(2)->get();
    //     $ciudades=Ciudades::all();

    //     return view('admin.createpacientes',array_merge(compact('estado','ciudades'),$datos, $datosGuia));
    // }

    //  /**
    //  * Detalle de un paciente específico.
    //  */
    // public function index_detallepaciente($paciente_id){
    //     $user=auth()->user();
    //     $datos=$this->usuarioService->DatosUsuario();

    //     $datosGuia = $this->obtenerDatosGuia();

    //     $ciudades=Ciudades::all();

    //     $paciente=Pacientes::with(['status','direccion','historial_clinico'])
    //         ->where('id',$paciente_id)->first();

    //     $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();

    //     $familiar=Familiar_paciente::with(['direccion'])
    //         ->where('paciente_id',$paciente_id)->get();

            
    //     $somatometria=Somatometria_paciente::where('paciente_id',$paciente_id)->first();

    //     $archivos = ArchivosPaciente::where('paciente_id', $paciente_id)->get();

    //     $infoArchivos = $this->planService->puedeSubirArchivosPacientes($paciente->clinica_id,$paciente->id);

    //     // dd($infoArchivos);


    //     return view('admin.detallepaciente', array_merge([
    //         'clinica'=>$user->clinicas,
    //         'paciente'=>$paciente,
    //         'observaciones'=>$observaciones,
    //         'estado_paciente'=>$paciente->status,
    //         'familiar'=>$familiar,
    //         'ciudades'=>$ciudades,
    //         'somatometria'=>$somatometria,
    //         'archivosPaciente' => $archivos,
    //         'infoArchivos' => $infoArchivos

    //     ], $datosGuia,$datos));

    // }

    //  /**
    //  * Muestra el expediente médico de un paciente.
    //  */
    // public function index_expediente($paciente_id, Request $request){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    

    //     $paciente=Pacientes::find($paciente_id);

    //     $expediente=Expedientes::with(['paciente','cita.servicio', 'personal.especialidad'])
    //         ->whereHas('paciente', function($q) use($paciente_id){
    //             $q->where('id',$paciente_id);
    //         })->orderBy('id','desc')->get();


    //     $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();

    //     $personal_medico = Personal::whereHas('usuario', function($q) use($datos) {
    //         $q->where('clinica_id', $datos['clinica_id']);
    //     })->where('puesto_id', 2)->get();

    //     $servicios = Servicio::where('clinica_id', $datos['clinica_id'])->get();

    //     $query = Expedientes::with(['paciente','cita.servicio', 'personal.especialidad'])
    //         ->whereHas('paciente', function ($q) use ($paciente_id) {
    //             $q->where('id', $paciente_id);
    //         });

    //     $filtrobusqueda = [];

    //     if ($request->filled('medico')) {
    //         $query->where('personal_id', $request->medico ?? null);
    //         $medico = Personal::find($request->medico ?? null);
    //         $filtrobusqueda[] = "Médico: " . ($medico ? $medico->nombre ?? null : "Desconocido");
    //     }

    //     if ($request->filled('fecha')) {
    //         $query->whereDate('fecha', $request->fecha ?? null);
    //         $filtrobusqueda[] = "Fecha: " . ($request->fecha ?? null);
    //     }

    //     if ($request->filled('servicio')) {
    //         $query->whereHas('cita', function ($q) use ($request) {
    //             $q->where('servicio_id', $request->servicio ?? null);
    //         });
    //         $servicio = Servicio::find($request->servicio ?? null );
    //         $filtrobusqueda[] = "Servicio: " . ($servicio ? $servicio->nombre ?? null: "Desconocido");
    //     }

    //     $expediente = $query->orderBy('fecha', 'desc')->get();

    //     $resultado = count($expediente) > 0
    //         ? "Se encontraron: " . count($expediente) . " consultas"
    //         : "No se encontraron resultados";

    //     if (!empty($filtrobusqueda)) {
    //         $resultado .= " para " . implode(", ", $filtrobusqueda);
    //     }

    //     return view('admin.expediente', array_merge(compact('expediente', 'personal_medico', 'servicios', 'resultado', 'paciente','expediente','observaciones'), $datos, $datosGuia));
    // }   

    // /**
    //  * Muestra el calendario de citas de la clínica.
    //  */
    // public function index_calendario(){
        
    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $citas=Citas::with(['personal.usuario','paciente','servicio','status'])
    //                 ->wherehas('personal.usuario',function($q) use($datos){
    //                     $q->where('clinica_id',$datos['clinica_id']);
    //                 })
    //                 ->get()
    //                 ->map(function ($cita){
    //                     return[
    //                         'id' => $cita->id,
    //                         'fecha_citaFormato'=>Carbon::parse($cita->fecha_cita ?? null)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
    //                         'fecha_cita' => $cita->fecha_cita ?? null,  
    //                         'hora_inicio' => $cita->hora_inicio ?? null,
    //                         'hora_fin' => $cita->hora_fin ?? null,
    //                         'paciente_id'=>$cita->paciente->id ?? null,
    //                         'nombre_paciente' => $cita->paciente->nombre ?? null,
    //                         'alias'=>$cita->paciente->alias ?? null,
    //                         'apellidoP_paciente' => $cita->paciente->apellido_paterno ?? null,
    //                         'apellidoM_paciente' => $cita->paciente->apellido_materno ?? null,
    //                         'nombre_medico' => $cita->personal->nombre ?? null,
    //                         'apellidoP_medico' => $cita->personal->apellido_paterno ?? null,
    //                         'apellidoM_medico' => $cita->personal->apellido_materno ?? null,
    //                         'servicio' => $cita->servicio->descripcion ?? null,
    //                         'status' => $cita->status->descripcion ?? null  
    //                     ];
    //                 });


    //     return view('admin.calendarioadmin', array_merge(compact('citas'),$datos, $datosGuia));
    // }

    // /**
    //  * Vista para agendar una nueva cita.
    //  */
    //  public function index_crearCitas(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     session(['previous_url' => url()->previous()]);

    //     $pacientes=Pacientes::where('status_id','=',1)
    //     ->where('clinica_id',$datos['clinica_id'])->get();
            
    //     $personal=Personal::whereHas('usuario',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id'])
    //             ->where('status_id',1);
    //     })->where('puesto_id',2)->get();
        
    //     $servicios=Servicio::where('clinica_id',$datos['clinica_id'])
    //         ->where('status_id',1)->get();

    //     return view('admin.crearcitas',array_merge(compact('pacientes','servicios','personal'),$datos, $datosGuia));
    // }

    // /**
    //  * Muestra listado de todas las citas de una clinica.
    //  */
    // public function index_citas(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    //     $conteoDatos = $this->conteoDatos();

    //     $personal_medico=Personal::whereHas('usuario',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->where('puesto_id',2)->get();

    //     $pacientes=pacientes::where('clinica_id',$datos['clinica_id'])->get();
        
    //     $citas=citas::whereHas('personal.usuario',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->orderBy('fecha_cita','asc')->get();  
    
    //     $resultado=count($citas)>0 ? "Se encontraron: ".count($citas). " citas" : "No se encontraron resultados"; 

    //     return view('admin.citaadmin',array_merge(compact('citas','personal_medico','pacientes','resultado'),$datos, $datosGuia, $conteoDatos));
    // }

    // /**
    //  * Búsqueda avanzada de citas por filtros: paciente, médico, estado, fecha.
    //  */
    // public function buscarCitas(Request $request){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    //     $conteoDatos = $this->conteoDatos();

    //     $personal_medico=Personal::whereHas('usuario',function($q) use($datos){
    //         $q->where('clinica_id',$datos['clinica_id']);
    //     })->where('puesto_id',2)->get();

    //     $pacientes=pacientes::where('clinica_id',$datos['clinica_id'])->get();

    //     $query=Citas::query()->orderBy('fecha_cita','desc');

    //     $query->whereHas('paciente', function($q) use ($datos) {
    //         $q->where('clinica_id', $datos['clinica_id']);
    //     });

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

    //     return view('admin.citaadmin', array_merge(compact('citas','personal_medico','pacientes','resultado'),$datos, $datosGuia, $conteoDatos));
    // }


    //  /**
    //  * Detalle completo de una cita médica, incluyendo expediente y observaciones.
    //  */
    // public function index_detalleCita($cita_id,$paciente_id){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    
    //     $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();

    //     $familiar_paciente=Familiar_paciente::where('paciente_id',$paciente_id)->first();
      
    //     $expediente=Expedientes::where('cita_id',$cita_id)->get();

    //     $cita=Citas::with(['servicio','personal','paciente'])
    //         ->where('id','=',$cita_id)->first();
        

    //     return view('admin.detallecitaadmin', array_merge([
    //         'cita' => $cita,
    //         'paciente' => $cita->paciente,
    //         'familiar_paciente'=>$familiar_paciente,
    //         'observaciones' => $observaciones,
    //         'clinica' => $datos['clinica'],
    //         'expediente' => $expediente, // Ya viene con with()
    //         'servicio' => $cita->servicio,
    //         'medico' => $cita->personal,
    //     ], $datosGuia));
    // }

   

    // /**
    //  * Muestra la vista de gestión de catálogos como especialidades, puestos y servicios.
    //  */

    // public function index_servicios(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    //     $conteoDatos = $this->conteoDatos();

    //     $puestos=Puesto::all();
    //     $servicios=Servicio::where('clinica_id',$datos['clinica_id'])->get();

    //     return view('admin.servicios', array_merge(compact('puestos','servicios'),$datos, $datosGuia, $conteoDatos));
    // }

    // public function buscarServicios(Request $request){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    //     $conteoDatos = $this->conteoDatos(); 

    //     $query = Servicio::where('clinica_id', $datos['clinica_id']);
    //     $filtrobusqueda = [];

    //     if ($request->filled('servicio')) {
    //         $query->where('id', '=', $request->servicio); 
    //         $servicio = Servicio::find($request->servicio);
    //         $filtrobusqueda[] = "Servicio: " . ($servicio ? $servicio->descripcion : "Desconocido");
    //     }

    //     if($request->filled('estado')){
    //         $query->where('status_id', $request->estado);
    //         $filtrobusqueda[]="Estado: ".ucfirst($request->estado);
    //     }

    //     $servicios = $query->get();

    //     $resultado = count($servicios) > 0
    //         ? "Se encontraron: " . count($servicios) . " servicios"
    //         : "No se encontraron resultados";

    //     if (!empty($filtrobusqueda)) {
    //         $resultado .= " para " . implode(", ", $filtrobusqueda);
    //     }

    //     return view('admin.servicios', array_merge(compact('servicios', 'resultado'), $datos, $datosGuia, $conteoDatos));
    // }

    // public function index_profesiones(){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $especialidades=Especialidad::where('clinica_id',$datos['clinica_id'])->get();
    //     $puestos=Puesto::all();

    //     return view('admin.profesiones', array_merge(compact('especialidades'),$datos, $datosGuia));
    // }
    
    // public function buscarProfesiones(Request $request){

    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();

    //     $query = Especialidad::where('clinica_id', $datos['clinica_id']);
    //     $filtrobusqueda = [];

    //     if ($request->filled('especialidad')) {
    //         $query->where('id', '=', $request->especialidad); 
    //         $especialidad = Especialidad::find($request->especialidad);
    //         $filtrobusqueda[] = "Profesión: " . ($especialidad ? $especialidad->descripcion : "Desconocido");
    //     }

    //     if($request->filled('estado')){
    //         $query->where('status_id', $request->estado);
    //         $filtrobusqueda[]="Estado: ".ucfirst($request->estado);
    //     }

    //     $especialidades = $query->get();

    //     $resultado = count($especialidades) > 0
    //         ? "Se encontraron: " . count($especialidades) . " servicios"
    //         : "No se encontraron resultados";

    //     if (!empty($filtrobusqueda)) {
    //         $resultado .= " para " . implode(", ", $filtrobusqueda);
    //     }

    //     return view('admin.profesiones', array_merge(compact('especialidades', 'resultado'), $datos, $datosGuia));
    // }

    // //avisos de privacidad
    // public function aviso_privacidad(){
    //     $datos=$this->usuarioService->DatosUsuario();

    //     return view('admin.AvisoPrivacidad',$datos);
    // }

    // //reportes

    // public function index_reportes(){
    //     $datos=$this->usuarioService->DatosUsuario();
    //     $datosGuia = $this->obtenerDatosGuia();
    //     $conteoDatos = $this->conteoDatos();

    //     $puestos=Puesto::all();
    //     $servicios=Servicio::where('clinica_id',$datos['clinica_id'])->get();

    //     return view('admin.reportes', array_merge(compact('puestos','servicios'),$datos, $datosGuia, $conteoDatos));
    
    // }

   
}
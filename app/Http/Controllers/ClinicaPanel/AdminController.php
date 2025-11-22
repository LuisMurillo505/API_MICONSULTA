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

/**
 * Obtiene estadísticas y límites de uso asociados a una clínica.
 *
 * Este método genera un conjunto de conteos relacionados con una clínica,
 * incluyendo usuarios, servicios, pacientes y citas. Además, compara estos
 * valores con los límites permitidos por el plan de suscripción de la clínica
 * para determinar cuánto espacio queda disponible en cada categoría.
 *
 * @param  int  $usuario_id  ID del usuario autenticado.
 * @return array  Retorna un arreglo con los conteos y límites calculados.
 */
    public function conteoDatos($usuario_id)
    {
        // Obtener los datos generales del usuario (como la clínica a la que pertenece)
        $datos=$this->usuarioService->DatosUsuario($usuario_id);

        //Conteo de usuarios activos en la clínica
       $conteoUsuarios=Personal::whereHas('usuario',function($q) use($datos){
            $q->where('clinica_id',$datos['clinica_id'])
                ->where('status_id',1);
        })->count();

        //Conteo de servicios activos en la clínica
        $conteoServicios=Servicio::where('clinica_id',$datos['clinica_id'])
        ->where('status_id',1)->count();

        // Obtener todos los servicios de la clínica (para listados o cálculos adicionales)
        $servicios=Servicio::where('clinica_id',$datos['clinica_id'])->get(); 

        //Conteo total de pacientes registrados en la clínica
        $conteoPacientes=pacientes::where('clinica_id',$datos['clinica_id'])->count();

        //Conteo total de citas (todas las citas asociadas a personal de la clínica)
        $conteoCitas=citas::whereHas('personal.usuario',function($q) use($datos){
            $q->where('clinica_id',$datos['clinica_id']);
        })->count();

        //Conteo de citas agendadas para el día de hoy
        $conteoCitasHoy=citas::whereHas('personal.usuario',function($q) use($datos){
            $q->where('clinica_id',$datos['clinica_id']);
        })->whereDate('fecha_cita', Carbon::today())->count();

        //Conteo de pacientes registrados hoy
        $conteoPacientesHoy=pacientes::where('clinica_id',$datos['clinica_id'])->whereDate('created_at', Carbon::today())->count();

        //Conteo de citas activas, finalizadas y canceladas
        $conteoActivas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 1)->count();

        $conteoFinalizadas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 3)->count();

        $conteoCanceladas = citas::whereHas('personal.usuario', function($q) use($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })->where('status_id', 4)->count();

        /**
         * Secciones para verificar los límites del plan de suscripción
         * Cada función_id representa una característica del plan (usuarios, servicios, pacientes, citas)
         */

        // === USUARIOS PERMITIDOS ===
        $conteoUsuariosP=$this->planService->usuariosPermitidos($datos['clinica_id'],$conteoUsuarios);

        // === SERVICIOS PERMITIDOS ===
        $conteoServiciosP=$this->planService->serviciosPermitidos($datos['clinica_id'],$conteoServicios);

        // === PACIENTES PERMITIDOS ===
        $conteoPacientesP=$this->planService->pacientesPermitidos($datos['clinica_id'],$conteoPacientes);

        // === CITAS PERMITIDAS ===
        $conteoCitasP=$this->planService->citasPermitidos($datos['clinica_id'],$conteoCitas);

        // Se devuelve un arreglo con todos los conteos y límites calculados
        return compact('conteoUsuarios', 'conteoServicios', 'servicios','conteoPacientes', 'conteoCitas', 'conteoCitasHoy', 'conteoPacientesHoy', 'conteoActivas', 'conteoFinalizadas', 'conteoCanceladas', 'conteoUsuariosP', 'conteoCitasP', 'conteoServiciosP', 'conteoPacientesP');
    }

/**
 * Muestra información general del usuario y su clínica.
 *
 * Este método obtiene y combina tres fuentes principales de datos:
 *  - Información general del usuario (DatosUsuario)
 *  - Información de guía o adicional (obtenerDatosGuia)
 *  - Estadísticas y conteos de la clínica (conteoDatos)
 *
 * Retorna una respuesta JSON que contiene todos estos datos integrados.
 *
 * @param  int  $usuario_id  ID del usuario autenticado.
 * @return \Illuminate\Http\JsonResponse  Respuesta JSON con los datos o un error.
 */
    public function index($usuario_id)
    {   
        try{
            //Obtener los datos generales del usuario,
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            //Obtener datos adicionales o guía (por ejemplo, información extendida del perfil).
            $datosGuia = $this->usuarioService->obtenerDatosGuia($usuario_id);

            //Obtener los conteos y estadísticas relacionadas con la clínica
            $conteoDatos = $this->conteoDatos($usuario_id);

            // Combinar toda la información obtenida en un solo arreglo.
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
 * Obtiene los datos necesarios para mostrar el perfil del usuario administrador.
 *
 * Este método recupera información relevante para la vista de perfil de un usuario,
 * incluyendo:
 *  - Lista completa de ciudades disponibles.
 *  - Datos de conexión con Google (si existen).
 *  - Permiso para usar Google Calendar según el plan de su clínica.
 *
 * Retorna una respuesta JSON con todos los datos combinados o un mensaje de error si algo falla.
 *
 * @param  int  $usuario_id  ID del usuario.
 * @return \Illuminate\Http\JsonResponse  Respuesta JSON con los datos del perfil o mensaje de error.
 */
    public function index_perfil($usuario_id){
   
        try{    
            //Buscar al usuario en la base de datos según su ID.
            $usuario=Usuario::find($usuario_id);

            //Obtener todas las ciudades disponibles en el sistema,
            $ciudades=Ciudades::all();

            //Obtener la configuración de Google asociada al usuario,
            $google=$usuario->google ?? null;

            // Verificar si la clínica asociada al usuario 
            // tiene permiso para usar la integración de Google Calendar.
            $googleCalendar=$this->planService->puedeUsarGoogleCalendar($usuario->clinicas->id);

            //Retornar los datos en formato JSON con estado de éxito.
            return response()->json([
                'success'=>true,
                'data'=> compact(
                    'ciudades',
                    'google',
                    'googleCalendar'
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
 * Obtiene la lista de usuarios (personal) asociados a la clínica del usuario autenticado.
 *
 * Este método devuelve un conjunto de datos con:
 *  - Lista completa del personal de la clínica.
 *  - Lista filtrada de usuarios según los parámetros enviados en la request (por ID o estado).
 *
 * Si se envían filtros opcionales:
 *  - `usuarios`: filtra por ID específico del personal.
 *  - `estado`: filtra por el estado del usuario (activo/inactivo, etc.).
 *
 * @param  \Illuminate\Http\Request  $request  Datos de la solicitud HTTP.
 * @param  int  $usuario_id  ID del usuario autenticado o que realiza la solicitud.
 * @return \Illuminate\Http\JsonResponse  Respuesta JSON con la lista de usuarios o mensaje de error.
 */
    public function index_usuarios(Request $request,$usuario_id)
    {   
        try{
            // Se obtiene el usuario principal que hace la solicitud,
            //Para identificar a qué clínica pertenece.
            $usuario=Usuario::find($usuario_id);

            // Se obtiene una lista completa de usuarios (personal) asociados
            //a la misma clínica del usuario.
            $Lista_usuarios=Personal::whereHas('usuario',function($q) use($usuario){
                $q->where('clinica_id',$usuario->clinica_id);
            })->get();

             /**
             * Se construye una consulta base para obtener el personal de la clínica,
             * incluyendo sus relaciones: especialidad, usuario y estado del usuario.
             */
            $query=Personal::query()->with(['especialidad', 'usuario','usuario.status'])
                ->whereHas('usuario', function($q) use($usuario){
                $q->where('clinica_id',$usuario->clinica_id);
            });

            /**
             * Si se proporciona un ID específico de usuario en la request,
             * se filtra la lista para mostrar solo ese usuario.
             */
            if($request->filled('usuarios')){
                $query->where('id',$request->usuarios);
            }

             /**
             * Si se proporciona un estado (activo/inactivo, etc.),
             * se filtran los usuarios cuyo status_id coincida.
             */
            if($request->filled('estado')){
                $query->whereHas('usuario', function($q) use($request) {
                    $q->where('status_id', $request->estado);
                });
            }

            //Se ejecuta la consulta y se obtiene la lista final de usuarios.
            $usuariosC = $query->get();  

            //Retornar los datos en formato JSON con estado de éxito.
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'usuariosC',
                    'Lista_usuarios'
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

    // /**
    //  * Muestra el formulario para registrar un nuevo usuario.
    //  */
    public function index_crearUsuario($usuario_id)  {

        try{
            // Se obtiene el usuario principal que hace la solicitud,
            //Para identificar a qué clínica pertenece.
            $usuario=Usuario::find($usuario_id);

            $especialidad=Especialidad::where('status_id',1)
                ->where('clinica_id',$usuario->clinica_id)->get();

            $puesto=Puesto::all();

            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'especialidad',
                    'puesto'
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

    // /**
    //  * Muestra la lista de pacientes y actualiza sus edades.
    //  */
    public function index_pacientes(Request $request,$usuario_id){

        try{
            //Obtener datos clave del usuario
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

          
            $Lista_paciente=Pacientes::where('clinica_id',$datos['clinica_id'])->get();

            $this->actualizarEdad($datos['clinica_id']);

            $query=Pacientes::query()->whereHas('status',function($q) use($datos){
                $q->where('clinica_id',$datos['clinica_id']);
            })->with('status');


            if($request->filled('paciente')){
                $query->where('id',$request->paciente);
            }

            if($request->filled('estado')){
                $query->whereHas('status',function($q) use($request){
                    $q->where('descripcion',$request->estado);
                });
            }
             // Filtrar por nombre (autocomplete)
            if ($request->ajax() && $request->filled('nombre')) {
                $pacientes = $query->where('nombre', 'like', $request->nombre . '%')
                    ->limit(10)
                    ->get(['id', 'nombre', 'foto', 'clinica_id']);

                // Modificar la foto y agregar nombre clínica usando $datos['nombre_clinica']
                foreach ($pacientes as $p) {
                    $p->foto = $p->foto
                        ? asset('storage/' . $datos['nombre_clinica'] . '/pacientes/' . $p->foto)
                        : asset('images/p1.webp');

                    // Opcional: agregar el nombre de la clínica en la respuesta
                    $p->nombre_clinica = $datos['nombre_clinica'];
                }

                return response()->json($pacientes);
            }

            $pacientes = $query->get(); 

            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'pacientes',
                    'Lista_paciente'
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
    public function actualizarEdad($clinica_id){

        $pacientes=Pacientes::where('clinica_id',$clinica_id)->get();

        foreach($pacientes as $pac){
            $edad=Carbon::Parse($pac->fecha_nacimiento)->age;
            $pac->update([
                'edad'=>$edad
            ]);
        }
        
    }

    //  /**
    //  * Vista de formulario para crear un nuevo paciente.
    //  */
    public function index_createpaciente(){

       try{

            $estado=Status::limit(2)->get();
            $ciudades=Ciudades::all();

            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'estado',
                    'ciudades'
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

    //  /**
    //  *  detallepaciente se encuentra en adminController-adminPanel/DetallePaciente
    //  */

    //  /**
    //  *  expediente se encuentra en adminController-adminPanel/Expediente
    //  */
    

    
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
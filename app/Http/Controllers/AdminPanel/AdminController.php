<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Clinicas;
use App\Models\Especialidad;
use App\Models\Payment;
use App\Models\Status;
use App\Models\Puesto;
use App\Models\Disponibilidad;
use App\Models\Personal;
use App\Models\ArchivosPaciente;
use App\Models\Expedientes;
use App\Models\Ciudades;
use App\Models\Funciones_planes;
use App\Models\Funciones;
use App\Models\Observaciones;
use App\Models\Familiar_paciente;
use App\Models\Somatometria_Paciente;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Validator;
use Carbon\Carbon;
use App\Models\Citas;
use App\Models\Pacientes;
use App\Models\UsuarioAdmin;
use App\Models\Servicio;
use App\Models\Planes;
use App\Models\StripeTarifas;
use App\Services\EconomiaService;
use App\Services\PlanService;


class AdminController extends Controller
{
    protected $economiaService;
    protected $planService;

    public function __construct(EconomiaService $economiaService, PlanService $planService){
        $this->economiaService=$economiaService;
        $this->planService=$planService;
    }

/** ConteoDatos
 * 
 * Obtiene diversos conteos de registros del sistema.
 *
 * Este método recopila información estadística general del sistema,
 * incluyendo el número total de clínicas, citas, pacientes, usuarios, etc.
 * También incluye conteos específicos del día actual (por ejemplo, citas y pacientes creados hoy).
 *
 */
    public function conteoDatos()
    {
        try {

            // Cuenta el total de clínicas registradas, incluyendo su relación con la tabla 'suscripcion'
            $conteoClinicas = Clinicas::with('suscripcion')->count();

             //Cuenta las citas que están agendadas para la fecha actual
            $conteoCitasHoy = Citas::whereDate('fecha_cita', Carbon::today())->count();

              //Cuenta los pacientes que se registraron el día de hoy
            $conteoPacientesHoy = Pacientes::whereDate('created_at', Carbon::today())->count();

            //Cuenta el total de usuarios del sistema (tipo general)
            $conteoUsuarios = Usuario::count();

            //Cuenta el total de usuarios administradores
            $conteoUsuariosAdmin = UsuarioAdmin::count();

            //Cuenta el total de pacientes registrados
            $conteoPacientes = Pacientes::count();

            //Cuenta el total de servicios disponibles
            $conteoServicios = Servicio::count();

             //Cuenta el total de citas (sin filtrar por fecha)
            $conteoCitas = Citas::count();

             //Cuenta el total de planes activos o registrados
            $conteoPlanes = Planes::count();

            //Cuenta el total de tarifas registradas en la tabla asociada a Stripe
            $conteoTarifaStripe = StripeTarifas::count();

             //Retorna una respuesta exitosa con todos los conteos en formato JSON
            return response()->json([
                'success' => true,
                'data' => compact(
                    'conteoClinicas',
                    'conteoCitasHoy',
                    'conteoPacientesHoy',
                    'conteoUsuarios',
                    'conteoUsuariosAdmin',
                    'conteoPacientes',
                    'conteoServicios',
                    'conteoCitas',
                    'conteoPlanes',
                    'conteoTarifaStripe'
                )
            ]);

        } catch (\Throwable $e) {
             // Si ocurre un error, devuelve una respuesta JSON con el mensaje y detalle del error
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conteos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

/**Index_Clinicas
 * 
 * Obtiene la lista completa de clínicas y el resumen de suscripciones asociadas.
 *
 * Este método consulta todas las clínicas junto con la información relacionada
 * de suscripciones (estado y plan), además de obtener un resumen de los planes
 * disponibles y cuántas clínicas activas están asociadas a cada plan.
 *
 * @return \Illuminate\Http\JsonResponse
 *  
 */
    public function index_clinicas(){
        try{
            // Obtener clínicas con sus relaciones de suscripción, estado y plan
            $clinicas = Clinicas::with('suscripcion.status','suscripcion.plan')->get();

            // Obtener planes con conteo de clínicas activas y sus relaciones de suscripción y estado
            $suscripciones = Planes::withCount(['suscripcion as total_clinicas' => function ($query) {
                $query->where('status_id', 1);
            }])->with('suscripcion.status')->get(['id','nombre']);

            // Retornar respuesta exitosa con los datos obtenidos
            return response()->json([
                'success' => true,
                'data'=>compact(
                    'clinicas',
                    'suscripciones')
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

/** Detalle_Clinica
 * 
 * Obtiene el detalle completo de una clínica específica.
 *
 * Este método recupera toda la información relacionada con una clínica,
 * incluyendo suscripciones, personal, pacientes, servicios, pagos,
 * citas, especialidades y el usuario administrador.
 * 
 * @param int $clinica_id
 *     ID de la clínica a consultar.
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 *     Si ocurre un error al obtener los datos desde la base de datos.
 */
    public function detalle_clinica($clinica_id){
        try{
            // Información general de la clínica y su suscripción
            $clinica = Clinicas::with(['suscripcion','suscripcion.plan', 'suscripcion.status'])
            ->find($clinica_id);

            // Usuario administrador de la clínica
            $usuarioAdmin=Usuario::with('clinicas')
                ->where('clinica_id',$clinica_id)->first();

            // Personal clínico con usuario, especialidad y estado
            $personalC=Personal::whereHas('usuario',function($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id);
            })->with('usuario','especialidad','usuario.status')->get();

             // Servicios registrados en la clínica
            $servicios=Servicio::with('status')->where('clinica_id',$clinica_id)->get();

             // Pacientes de la clínica
            $pacientes=Pacientes::with('status')
                ->where('clinica_id',$clinica_id)->get();

            // Pagos realizados por la clínica
            $pagos=Payment::with('plan')
                ->where('clinica_id',$clinica_id)->get();

            // Citas asociadas a la clínica
            $citas=Citas::whereHas('personal.usuario',function($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id);
            })->with('personal','paciente','status')->orderBy('fecha_cita','asc')->get();

            // Especialidades registradas en la clínica
            $especialidades=Especialidad::with('status')->
                where('clinica_id',$clinica_id)->get();

            // Respuesta exitosa con toda la información recopilada
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'clinica',
                   'personalC',
                    'pacientes',
                    'pagos',
                    'usuarioAdmin',
                    'servicios',
                    'citas',
                    'especialidades'
                )
            ]);

        }catch(\Throwable $e){
            // Manejo de errores con mensaje y detalles de la excepción
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

/** DetalleUsuario
 * 
 * Obtiene el detalle completo de un usuario específico y su información relacionada.
 *
 * Este método recupera toda la información asociada a un usuario del sistema,
 * incluyendo su estado, clínica, especialidad, puesto y disponibilidad laboral.
 *
 * @param int $usuario_id
 *     ID del usuario a consultar.
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 *     Si ocurre un error al obtener los datos desde la base de datos.
 */
    public function index_detalleusuario($usuario_id){
        try{
            // Obtener usuario con su estado y clínica asociada
            $usuarioP=Usuario::with('status','clinicas')->find($usuario_id);
       
            // Obtener todas las especialidades disponibles en la clínica del usuario
            $especialidad=Especialidad::where('clinica_id',$usuarioP->clinica_id)
            ->where('status_id',1)->get();

            // Obtener todos los puestos disponibles
            $puesto=Puesto::all();

            // Obtener el registro de personal asociado al usuario
            $personal=Personal::where('usuario_id',$usuario_id)->first();

            // Obtener la especialidad y el puesto específicos del usuario
            $especialidad_user=Especialidad::where('id',$personal->especialidad_id)->first();
            $puesto_user=Puesto::where('id',$personal->puesto_id)->first();

            // Obtener disponibilidad laboral del usuario por día
            $disponibilidad=Disponibilidad::where('personal_id',$personal->id)->get()->keyBy('dia');

            //verifica si el usuario medico puede y estar conectado con googleCalendar
            $googleCalendar=$this->planService->puedeUsarGoogleCalendar($usuarioP->clinica_id);
            $google=$personal->usuario->google;

            // Retornar respuesta exitosa con todos los datos recopilados
            return response()->json([
                'success'=>true,
                'data'=>compact('usuarioP','especialidad',
                'puesto','personal','especialidad_user','puesto_user',
                'disponibilidad','googleCalendar','google')]);

        }catch(\Throwable $e){
            // Manejo de errores con detalles de la excepción
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }

/** DetallePaciente
 * Obtiene el detalle completo de un paciente específico.
 *
 * Este método recupera toda la información relacionada con un paciente,
 * incluyendo sus datos personales, dirección, historial clínico, familiares,
 * observaciones médicas, somatometría, y archivos asociados.
 * Además, verifica la capacidad de la clínica para subir nuevos archivos
 * según el plan de suscripción actual.
 *
 * @param int $paciente_id
 *     ID del paciente a consultar.
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 *     Si ocurre un error al obtener los datos desde la base de datos o servicios externos.
 */

    public function index_detallepaciente($paciente_id){
   
        try{
            // Catálogo general de ciudades
            $ciudades=Ciudades::all();

            // Información principal del paciente con sus relaciones
            $paciente=Pacientes::with(['status','direccion','historial_clinico','clinicas'])
                ->where('id',$paciente_id)->first();

            // Observaciones médicas registradas del paciente
            $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();

            // Familiares del paciente con su respectiva dirección
            $familiar=Familiar_paciente::with(['direccion'])
                ->where('paciente_id',$paciente_id)->get();

            // Conteo total de familiares asociados
            $totalFamiliares = $familiar->count();

            // Datos somatométricos del paciente
            $somatometria=Somatometria_paciente::where('paciente_id',$paciente_id)->first();

            // Archivos clínicos del paciente
            $archivosPaciente = ArchivosPaciente::where('paciente_id', $paciente_id)->get();

            // Validar capacidad de subida de archivos según plan de la clínica
            $infoArchivos = $this->planService->puedeSubirArchivosPacientes($paciente->clinica_id,$paciente->id);

            // Retornar respuesta exitosa con toda la información
            return response()->json([
                'success'=>true,
                'data'=>compact(
                'paciente',
                'observaciones',
                'familiar',
                'totalFamiliares',
                'ciudades',
                'somatometria',
                'archivosPaciente',
                'infoArchivos'
            )]);
        }catch(\Throwable $e){
            // Manejo de errores y respuesta con mensaje descriptivo
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Búsqueda de pacientes por ID o estado.
     */
    // public function buscarPaciente(Request $request){
        
    //     $conteoDatos = $this->conteoDatos();

    //     $Lista_paciente=Pacientes::all();

    //     $query=Pacientes::query();

    //     $query = Pacientes::all();

    //     if($request->filled('paciente')){
    //         $query->where('id',$request->paciente);
    //     }

    //     if($request->filled('estado')){
    //         $query->whereHas('status',function($q) use($request){
    //             $q->where('descripcion',$request->estado);
    //         });
    //     }
        
    //    // Filtrar por nombre (autocomplete)
    //     if ($request->ajax()) {
    //         $pacientes = $query->where('nombre', 'like', $request->nombre . '%')
    //             ->limit(10)
    //             ->get(['id', 'nombre', 'foto', 'clinica_id']);

                
    //         return response()->json($pacientes);
    //     }
    //     // $pacientes = $query->get(); 

    //     return view('admin.pacientes', array_merge(compact('pacientes','Lista_paciente')));
    // }

/** Expediente
 * 
 * Obtiene el historial clínico (expediente médico) completo de un paciente.
 *
 * Este método recupera todos los expedientes médicos de un paciente específico,
 * permitiendo aplicar filtros por médico, fecha o servicio, además de incluir
 * información contextual como observaciones, servicios disponibles y personal médico.
 *
 * @param int $paciente_id
 *     ID del paciente del cual se desea obtener el expediente.
 * @param \Illuminate\Http\Request $request
 *     Objeto Request que puede contener filtros opcionales ('medico', 'fecha', 'servicio').
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 *     Si ocurre un error al obtener los datos desde la base de datos o durante el filtrado.
 */
    public function index_expediente($paciente_id, Request $request){

        try{

             // Obtener paciente y su clínica asociada
            $paciente=Pacientes::with('clinicas')->find($paciente_id);

            // Obtener expedientes del paciente con relaciones relevantes
            $expediente=Expedientes::with(['paciente','cita','cita.servicio','personal', 'personal.especialidad'])
                ->whereHas('paciente', function($q) use($paciente_id){
                    $q->where('id',$paciente_id);
                })->orderBy('id','desc')->get();


            // Observaciones clínicas del paciente 
            $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();

            // Personal médico de la clínica (puesto_id = 2)
            $personal_medico = Personal::whereHas('usuario', function($q) use($paciente) {
                $q->where('clinica_id', $paciente->clinica_id);
            })->with('usuario.clinicas')->where('puesto_id', 2)->get();

            // Servicios disponibles en la clínica
            $servicios = Servicio::where('clinica_id', $paciente->clinica_id)->get();

            // Configurar consulta base para aplicar filtros
            $query = Expedientes::with(['paciente','cita.servicio', 'personal.especialidad'])
                ->whereHas('paciente', function ($q) use ($paciente_id) {
                    $q->where('id', $paciente_id);
                });

            $filtrobusqueda = [];

            // Filtro: médico
            if ($request->filled('medico')) {
                $query->where('personal_id', $request->medico ?? null);
                $medico = Personal::find($request->medico ?? null);
                $filtrobusqueda[] = "Médico: " . ($medico ? $medico->nombre ?? null : "Desconocido");
            }

            // Filtro: fecha
            if ($request->filled('fecha')) {
                $query->whereDate('fecha', $request->fecha ?? null);
                $filtrobusqueda[] = "Fecha: " . ($request->fecha ?? null);
            }

            // Filtro: servicio
            if ($request->filled('servicio')) {
                $query->whereHas('cita', function ($q) use ($request) {
                    $q->where('servicio_id', $request->servicio ?? null);
                });
                $servicio = Servicio::find($request->servicio ?? null );
                $filtrobusqueda[] = "Servicio: " . ($servicio ? $servicio->nombre ?? null: "Desconocido");
            }

            // Ejecutar consulta final con filtros aplicados
            $expediente = $query->orderBy('fecha', 'desc')->get();

            // Mensaje de resultado descriptivo
            $resultado = count($expediente) > 0
                ? "Se encontraron: " . count($expediente) . " consultas"
                : "No se encontraron resultados";

            if (!empty($filtrobusqueda)) {
                $resultado .= " para " . implode(", ", $filtrobusqueda);
            }

            // Retornar respuesta exitosa con todos los datos
            return response()->json([
            'sucess'=>true,
            'data'=>compact('expediente', 'personal_medico', 
                'servicios', 'resultado', 'paciente','expediente','observaciones')]);

        }catch(\Throwable $e){
            // Manejo de errores y respuesta con mensaje descriptivo
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
       
    }

 /** Detalle Cita
 * Muestra el detalle de una cita médica junto con la información relacionada del paciente.
 *
 * Este método obtiene información asociada a una cita específica, incluyendo:
 * - Datos del paciente
 * - Información del familiar
 * - Observaciones médicas
 * - Expediente clínico
 * - Detalles de la cita (servicio, personal, clínica y estatus)
 *
 * @param  int  $cita_id        ID de la cita médica.
 * @param  int  $paciente_id    ID del paciente asociado a la cita.
 *
 * @return \Illuminate\Http\JsonResponse  Respuesta en formato JSON con los datos obtenidos o el error ocurrido.
 *
 * @throws \Throwable  Si ocurre algún error durante la obtención de los datos.
 *
 */
     public function index_detalleCita($cita_id){
    
        try{
            //Obtiene la información completa de la cita, incluyendo sus relaciones:
            //servicios,paciente,personal,status,clinicas
            $cita=Citas::with(['servicio','personal','paciente','paciente.clinicas','status'])
                ->where('id','=',$cita_id)->first();
            
            // Obtiene todas las observaciones médicas registradas para el paciente.
            $observaciones=Observaciones::where('paciente_id',$cita->paciente_id)->get();

            //Obtiene la información del familiar asociado al paciente.
            $familiar_paciente=Familiar_paciente::where('paciente_id',$cita->paciente_id)->first();
        
             //Recupera el expediente clínico correspondiente a la cita médica.
            $expediente=Expedientes::where('cita_id',$cita_id)->get();

          

            //Retorna la respuesta en formato JSON con los datos recopilados.
            return response()->json([
                'success'=>true,
                'data'=>compact( 
                'cita',
                'familiar_paciente',
                'observaciones',
                'expediente', 
            )]);

        }catch(\Throwable  $e){
            // Manejo de errores y respuesta con mensaje descriptivo
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);        
        }
        
    }

/**
 * Calendario
 * Muestra todas las citas médicas registradas en formato adecuado para el calendario.
 *
 * Este método obtiene todas las citas desde la base de datos junto con sus relaciones
 * (médico, paciente, servicio y estado). Luego, transforma los datos para incluir
 * información legible del paciente, médico, servicio, estado y la clínica correspondiente.
 *
 * @return \Illuminate\Http\JsonResponse Respuesta JSON con la lista de citas formateadas o mensaje de error.
 *
 * @throws \Throwable Si ocurre algún error durante la consulta o transformación de los datos.
 *
 */
     public function index_calendario(){
        try{
            //obtiene todas las citas de todas las clinicas
            $citas=Citas::with(['personal.usuario','paciente','servicio','status'])
                ->get()
                ->map(function ($cita){
                    return[
                        'id' => $cita->id,
                        'clinica'=> $cita->personal->usuario->clinicas->nombre,
                        'fecha_citaFormato'=>Carbon::parse($cita->fecha_cita ?? null)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                        'fecha_cita' => $cita->fecha_cita ?? null,  
                        'hora_inicio' => $cita->hora_inicio ?? null,
                        'hora_fin' => $cita->hora_fin ?? null,
                        'paciente_id'=>$cita->paciente->id ?? null,
                        'nombre_paciente' => $cita->paciente->nombre ?? null,
                        'alias'=>$cita->paciente->alias ?? null,
                        'apellidoP_paciente' => $cita->paciente->apellido_paterno ?? null,
                        'apellidoM_paciente' => $cita->paciente->apellido_materno ?? null,
                        'nombre_medico' => $cita->personal->nombre ?? null,
                        'apellidoP_medico' => $cita->personal->apellido_paterno ?? null,
                        'apellidoM_medico' => $cita->personal->apellido_materno ?? null,
                        'servicio' => $cita->servicio->descripcion ?? null,
                        'status' => $cita->status->descripcion ?? null  
                    ];
                });
                
            // Retornar respuesta exitosa con todos los datos
            return response()->json([
                'success'=>true,
                'data'=>compact('citas')
            ]);

        }catch(\Throwable  $e){
            // Manejo de errores y respuesta con mensaje descriptivo
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }

       
    }

/**
 * UsuariosAdmin
 * Obtiene la lista de usuarios administradores junto con su estado.
 *
 * Este método consulta todos los registros del modelo `UsuarioAdmin`
 * incluyendo la relación `status`, y retorna los datos en formato JSON.
 * 
 * En caso de error durante la consulta, captura la excepción y devuelve
 * una respuesta JSON con el mensaje de error correspondiente.
 *
 * @return \Illuminate\Http\JsonResponse Respuesta JSON.
 *
 * @throws \Throwable Si ocurre un error durante la obtención de datos.
 */
    public function index_usuariosAdmin(){
        try{
            
            $usuariosAdmin=UsuarioAdmin::with('status')->get();

            // Retornar respuesta exitosa con todos los datos
            return response()->json([
                'success'=>true,
                'data'=>compact('usuariosAdmin')
            ]);

        }catch(\Throwable $e){
            // Manejo de errores: retorna mensaje y detalle del error
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);        
        }
    }

/**
 * Planes
 * Obtiene la lista de planes y sus funciones disponibles.
 *
 * Este método recupera todos los registros del modelo `Planes` y del modelo `Funciones`,
 * retornando la información en formato JSON. Está diseñado para proporcionar
 * los datos necesarios para la gestión o visualización de planes y sus características.
 *
 * En caso de que ocurra un error durante la obtención de los datos, se captura la excepción
 * y se devuelve una respuesta JSON con el mensaje de error correspondiente.
 *
 * @return \Illuminate\Http\JsonResponse Respuesta JSON con:
 * - `success`: indica si la operación fue exitosa.
 * - `data`: contiene los arreglos `planes` y `funciones`.
 * - `error`: (solo en caso de error) detalle de la excepción.
 *
 * @throws \Throwable Si ocurre un error durante la obtención de datos.
 */
    public function index_planes(){
        try{
            
            //obtiene todos los planes
            $planes=Planes::all();

            //obtiene las funciones de los planes (servicios,usuarios,citas,etc)
            $funciones=Funciones::all();

            // Retornar respuesta exitosa con todos los datos
            return response()->json([
                'success'=>true,
                'data'=>compact('planes','funciones')
            ]);

        }catch(\Throwable $e){
            // Manejo de errores: retorna mensaje y detalle del error
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);          }
    }

/**
 * DetallePlan
 * Obtiene los detalles de un plan específico junto con sus funciones asociadas.
 *
 * Este método busca un registro del modelo `Planes` según el identificador proporcionado
 * y recupera las funciones asociadas al plan desde el modelo `Funciones_planes`
 * incluyendo la relación con `funcion`.
 *
 * En caso de error durante la consulta, se captura la excepción y se devuelve una
 * respuesta JSON con el mensaje y detalle del error.
 *
 * @param  int  $plan_id  Identificador único del plan a consultar.
 * 
 * @return \Illuminate\Http\JsonResponse Respuesta JSON con:.
 *
 * @throws \Throwable Si ocurre un error durante la obtención de datos.
 */
    public function detalle_plan($plan_id){
        try{
            
            //obtiene un plan en especifico
            $plan=Planes::find($plan_id);

            //obtiene las funciones del plan
            $funcionPlan=Funciones_planes::with('funcion')
                ->where('plan_id',$plan_id)->get();

            // Retornar respuesta exitosa con todos los datos
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'plan',
                    'funcionPlan'
                )
            ]);
        }catch(\Throwable $e){
            // Manejo de errores: retorna mensaje y detalle del error
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);         
        }
    }

/** StripeTarifas
 * Obtiene la lista de tarifas configuradas para Stripe junto con su estado.
 *
 * Este método consulta todos los registros del modelo `StripeTarifas`,
 * incluyendo la relación `status`, y devuelve la información en formato JSON.
 * 
 * Es útil para mostrar las tarifas activas o configuradas dentro del sistema
 * que interactúa con la API de Stripe.
 *
 * En caso de error durante la obtención de los datos, se captura la excepción
 * y se devuelve una respuesta JSON con el mensaje y detalle del error.
 *
 * @return \Illuminate\Http\JsonResponse Respuesta JSON con:
 * 
 * @throws \Throwable Si ocurre un error durante la obtención de datos.
 */
    public function index_stripeTarifas(){
        try{
            
            //obtiene las tarifas ingresadas de stripe
            $TarifaStripe=StripeTarifas::with('status')->get();

            // Retornar respuesta exitosa con todos los datos
            return response()->json([
                'success'=>true,
                'data'=>compact('TarifaStripe')
            ]);

        }catch(\Throwable $e){
            // Manejo de errores: retorna mensaje y detalle del error
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);              }
    }


/**
 * Obtiene información general de reportes administrativos del sistema.
 *
 * Este método genera un resumen de métricas financieras y de suscripciones
 * de las clínicas, incluyendo:
 * - Ingresos totales obtenidos mediante el servicio de economía.
 * - Planes con clínicas activas.
 * - Planes con suscripciones próximas a finalizar.
 * - Planes con suscripciones inactivas.
 *
 * Las suscripciones se agrupan según su `status_id`:
 * - 1 → Activas
 * - 6 → Por terminar
 * - 2 → Inactivas
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 *     Si ocurre un error al obtener los datos o al comunicarse con el servicio de economía.
 */ 
     public function index_reportes(){
        try{

            // Obtener ingresos desde el servicio de economía
            $ingresos= $this->economiaService->ingresos();
        
            // Suscripciones activas (status_id = 1)
            $suscripcionesActivas = Planes::withCount(['suscripcion as total_clinicas' => function ($query) {
                $query->where('status_id', 1);
            }])->get(['id', 'nombre']);

            $suscripcionesActivas->transform(function($item){
                $item->status_id = 1;
                return $item;
            });

            // Suscripciones por terminar (status_id = 6)
            $suscripcionesPorTerminar=Planes::withCount(['suscripcion as total_clinicas' => function ($query) {
                $query->where('status_id', 6);
            }])->get(['id', 'nombre']);

            $suscripcionesPorTerminar->transform(function($item){
                $item->status_id = 6;
                return $item;
            });

            // Suscripciones inactivas (status_id = 2)
             $suscripcionesInactivas=Planes::withCount(['suscripcion as total_clinicas' => function ($query) {
                $query->where('status_id', 2);
            }])->get(['id', 'nombre']);

             $suscripcionesInactivas->transform(function($item){
                $item->status_id = 2;
                return $item;
            });

            // Retornar respuesta exitosa con los datos del reporte
            return response()->json([ 
                'success'=>true,
                'data'=>compact('suscripcionesActivas',
                'suscripcionesPorTerminar','suscripcionesInactivas','ingresos')]);

        }catch(\Throwable $e){
             // Manejo de errores con detalles de la excepción
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

/**Detalle Reporte
 * Obtiene el detalle del reporte de clínicas según un plan y un estado de suscripción.
 *
 * Este método busca las clínicas que tienen una suscripción asociada a un plan y estado específicos.
 * Además, obtiene el total de clínicas asociadas a dicho plan filtradas por el estado dado.
 * Finalmente, devuelve una respuesta JSON con la información de las clínicas y suscripciones.
 *
 * @param  int  $plan_id     ID del plan a consultar.
 * @param  int  $status_id   ID del estado de suscripción a consultar.
 * 
 * @return \Illuminate\Http\JsonResponse  Respuesta en formato JSON con el detalle del reporte.
 *
 * @throws \Throwable Si ocurre un error durante la obtención de los datos.
 */
    public function detalle_reporte($plan_id,$status_id){
        try{

            // Obtiene las clínicas con sus suscripciones que coinciden con el plan y estado especificados
            $clinicas=Clinicas::whereHas('suscripcion',function($q) use($plan_id,$status_id){
                $q->where('plan_id',$plan_id);
                $q->where('status_id',$status_id);
            })->with('suscripcion.plan','suscripcion.status')->get();

            // Obtiene las suscripciones del plan con el conteo de clínicas activas según el estado
            $suscripciones = Planes::withCount(['suscripcion as total_clinicas' => function ($query) use($status_id) {
                $query->where('status_id', $status_id);
            }])->where('id',$plan_id)->get(['id', 'nombre']);

            // Obtiene la descripción del estado
            $status=Status::find($status_id);

            
            // Agrega la descripción del estado al resultado de las suscripciones
            $suscripciones->transform(function($item) use($status){
                $item->status = $status->descripcion;
                return $item;
            });

            // Retorna los datos en formato JSON
            return response()->json([
                'success'=>true,
                'data'=>compact('clinicas','suscripciones')
            ]);

        }catch(\Throwable $e){
            // Manejo de errores: retorna mensaje y detalle del error
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    

 
    //Registro de un nuevo usuario
    public function Register(Request $request){

        $validator=Validator::make($request->all(),[
            'nombre_usuario'=>'required|string|max:50',
            'password'=>'required|string|min:8|confirm',
            'email'=>'required|email'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        //crear usuario
        $user=Usuario::create([
            'nombre_usuario'=>$request->nombre_usuario,
            'password'=>Hash::make($request->password),
            'created_at'=>now(),
            'updated_at'=>now()
        ]);

        //generar un token
        $token=$user->createToken('UsuarioTeorosi')->plainTextToken;

        return response()->json([
            'message'=>'Usuario Creado Con Exito',
            'Usuario'=>$user,
            'token'=>$token
        ]);

    }

    //Login de usuario
    public function Login(Request $request){
        
        $validator=Validator::make($request->all(),[
            'correo'=>'required|email',
            'password'=>'required|string'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        if(auth()->attempt(['correo'=>$request->correo,'password'=>$request->password])){
            $user=auth()->user();
            $token=$user->createToken('UsuarioTerosi')->plainTextToken;

            return response()->json([
                'message'=>'Login con Exito',
                'user'=>$user,
                'token'=>$token
            ]);
        }

        return response()->json(['message'=>'No autorizado',401]);
    }

    //Logout
    public function Logout(Request $request){

        $request->user()->tokens->each(function($token){
            $token->delete();
        });

        return response()->json(['message'=>'Se Cerro Sesión']);
    }
}

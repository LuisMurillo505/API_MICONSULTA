<?php

namespace App\Http\Controllers\ClinicaPanel;
 
use App\Http\Controllers\Controller;
use App\Services\UsuarioService;
use App\Models\Especialidad;
use App\Models\Servicio;
use App\Models\Puesto;  
use App\Models\Usuario;
use App\Models\Personal;
use App\Models\Pacientes;
use App\Models\Status;
use App\Models\Citas;
use App\Models\Ciudades;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


/**
 * Controlador AdminController
 * Responsable de gestionar las vistas y operaciones relacionadas con la administración
 * de la clínica: usuarios, pacientes, citas, expedientes y catálogos.
 */
class AdminController extends Controller 
{
    protected $usuarioService;
    protected $planService;
    
    public function __construct(UsuarioService $usuarioServices, PlanService $planServices)
    {
        $this->usuarioService = $usuarioServices;
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
    public function conteoDatos(int $usuario_id)
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
        // $servicios=Servicio::where('clinica_id',$datos['clinica_id'])->get(); 

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
        return compact('conteoUsuarios', 'conteoServicios','conteoPacientes', 'conteoCitas', 'conteoCitasHoy', 'conteoPacientesHoy', 'conteoActivas', 'conteoFinalizadas', 'conteoCanceladas', 'conteoUsuariosP', 'conteoCitasP', 'conteoServiciosP', 'conteoPacientesP');
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
    public function index(int $usuario_id)
    {   
        try{
            //Obtener los datos generales del usuario,
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            //Obtener datos adicionales o guía (por ejemplo, información extendida del perfil).
            $datosGuia = $this->usuarioService->obtenerDatosGuia($usuario_id);

            //Obtener los conteos y estadísticas relacionadas con la clínica
            $conteoDatos = $this->conteoDatos($usuario_id);

            $pacientesform=Pacientes::where('clinica_id',$datos['clinica_id'])->get();

            $medicoForm=Personal::whereHas('usuario',function($q) use($datos){
                $q->where('clinica_id',$datos['clinica_id']);
            })->where('puesto_id','!=',1)->get();

            $serviciosForm=Servicio::with('status')->where('clinica_id',$datos['clinica_id'])->get();


            $adminMedico=false;
            if($datos['personal_id']){
                $adminMedico=true;
            }

            $especialidad=Especialidad::where('status_id',1)
                ->where('clinica_id',$datos['clinica_id'])->get();


            // Combinar toda la información obtenida en un solo arreglo.
            return response()->json([
                'success' => true,
                'data'=>array_merge(
                    (array)$datos,
                    (array)$datosGuia,
                    (array)$conteoDatos,
                    compact('adminMedico','especialidad','pacientesform','medicoForm','serviciosForm'))
                    
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
    public function index_perfil(int $usuario_id){
   
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

//----------------------------------------Usuarios-------------------------------------------

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
    public function index_usuarios(Request $request,int $usuario_id)
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

/**
 * Obtiene la información necesaria para la creación de un nuevo usuario,
 * incluyendo especialidades y puestos disponibles, filtrados según la clínica
 * del usuario que realiza la solicitud.
 *
 * @param  int  $usuario_id  ID del usuario que solicita la información.
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable  Captura cualquier excepción ocurrida durante la consulta.
 */
    public function index_crearUsuario(int $usuario_id)  {

        try{
            // Se obtiene el usuario principal que hace la solicitud,
            //Para identificar a qué clínica pertenece.
            $usuario=Usuario::find($usuario_id);

            //obtiene las profesiones de la clinica
            $especialidad=Especialidad::where('status_id',1)
                ->where('clinica_id',$usuario->clinica_id)->get();

            //obtiene los puestos en el sistema(medico o recepcion)
            $puesto=Puesto::limit(2)->get();

            //Retornar los datos en formato JSON con estado de éxito.
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
//------------------------------------------Pacientes-------------------------------------------------

/**
 * Obtiene y filtra la lista de pacientes asociados a la clínica del usuario.
 * Permite filtrado por paciente, estado y autocompletado por nombre.
 *
 * @param  \Illuminate\Http\Request  $request   Petición HTTP con filtros opcionales.
 * @param  int  $usuario_id                      ID del usuario solicitante.
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable  Captura cualquier error durante el proceso.
 */
    public function index_pacientes(Request $request,int $usuario_id){

        try{
            //Obtener datos clave del usuario
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            // Lista completa de pacientes de la clínica
            $Lista_paciente=Pacientes::where('clinica_id',$datos['clinica_id'])->get();

             // Actualizar la edad calculada de todos los pacientes de la clínica
            $this->actualizarEdad($datos['clinica_id']);

            // Query base: pacientes cuyo status pertenece a la clínica del usuario
            $query=Pacientes::query()->whereHas('status',function($q) use($datos){
                $q->where('clinica_id',$datos['clinica_id']);
            })->with('status');


            // Filtro por ID de paciente
            if($request->filled('paciente')){
                $query->where('id',$request->paciente);
            }

            // Filtro por estado del paciente
            if($request->filled('estado')){
                $query->whereHas('status',function($q) use($request){
                    $q->where('descripcion',$request->estado);
                });
            }

            //Ejecutar la consulta final
            $pacientes = $query->get(); 

            //Retornar los datos en formato JSON con estado de éxito.
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
/**
 * Busca y filtra pacientes por nombre (función de autocompletado) dentro de la clínica
 * asociada al usuario proporcionado.
 *
 * @param \Illuminate\Http\Request $request Contiene los datos de la petición, esperando el campo 'nombre'.
 * @param int $usuario_id El ID del usuario que realiza la búsqueda, utilizado para determinar la 'clinica_id'.
 *
 * @return \Illuminate\Http\JsonResponse Retorna una respuesta JSON.
 * - **Éxito (200):** Un array de objetos Paciente (limitado a 5 resultados).
 * - **Fallo (500):** Un objeto con 'success' => false y detalles del error.
 */
    public function buscarPaciente(Request $request,int $usuario_id){
        try{
            //Obtener los datos del usuario
            $datos=$this->usuarioService->DatosUsuario($usuario_id);
    
            //Iniciar la consulta, filtrando por clínica
            $query = Pacientes::query()->where('clinica_id', $datos['clinica_id']);    

            // Aplicar el filtro de autocompletado por nombre y limitar resultados
            $pacientes = $query->where('nombre', 'like', $request->nombre . '%')
                ->limit(5)
                ->get(['id', 'nombre', 'foto', 'clinica_id']);

            // Retornar la lista de pacientes encontrados
            return response()->json($pacientes);

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
 * Calcula la edad actual de todos los pacientes de una clínica específica
 * y actualiza el campo 'edad' en la base de datos para cada uno.
 *
 * Este método es típicamente diseñado para ser ejecutado periódicamente (por ejemplo,
 * a través de un comando de consola o un Job de Laravel).
 *
 * @param int $clinica_id El ID de la clínica cuyos pacientes serán actualizados.
 *
 * @return void La función no retorna ningún valor explícito, realiza la acción de actualización directamente.
 */
    public function actualizarEdad(int $clinica_id){

        //Obtener todos los pacientes de la clínica especificada
        $pacientes=Pacientes::where('clinica_id',$clinica_id)->get();

        //Iterar sobre cada paciente para calcular y actualizar la edad
        foreach($pacientes as $pac){
            $edad=Carbon::Parse($pac->fecha_nacimiento)->age;
            $pac->update([
                'edad'=>$edad
            ]);
        }
        
    }

/**
 * Obtiene los datos necesarios para la vista o formulario de creación de un nuevo paciente.
 * * Específicamente, recupera una lista de estados (limitada a 2 registros) y una lista
 * completa de ciudades desde la base de datos.
 *
 * @return \Illuminate\Http\JsonResponse 
 * * Retorna una respuesta JSON que contiene:
 * - Si es exitosa: Un objeto con 'success' => true y 'data' con las colecciones 'estado' y 'ciudades'.
 * - Si hay un error: Un objeto con 'success' => false, un 'message' de error, y los detalles del 'error' capturado.
 * * El código de estado HTTP es 200 en caso de éxito y 500 en caso de error.
 */
    public function index_createpaciente(){

       try{

            // Obtener los primeros 2 registros de la tabla 'Status'(Activo e Inactivo)
            $estado=Status::limit(2)->get();

            // Obtener todos los registros de la tabla 'Ciudades'
            $ciudades=Ciudades::all();

            // Respuesta JSON exitosa con los datos
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


//----------------------------------------Servicios-----------------------------------------

/**
 * Recupera y filtra la lista de servicios asociados a una clínica específica.
 * * La función utiliza el ID del usuario para obtener la clínica de pertenencia y luego
 * consulta los servicios, aplicando filtros opcionales proporcionados en la solicitud HTTP.
 * * @param \Illuminate\Http\Request $request Objeto de solicitud HTTP. Puede contener los filtros 'servicio' (ID de servicio) y 'estado' (ID de estado/status).
 * @param int $usuario_id El ID del usuario que realiza la solicitud. Se utiliza para obtener el ID de la clínica a través del servicio 'usuarioService'.
 * * @return \Illuminate\Http\JsonResponse 
 * * Retorna una respuesta JSON que contiene:
 * * @throws \Throwable Si ocurre cualquier error durante la obtención de datos o la ejecución del servicio de usuario.
 */

    public function index_servicios(Request $request,int $usuario_id){

        try{
            //Obtener la información detallada del usuario
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            //Se recupera la colección completa de todos los puestos disponibles.
            $puestos=Puesto::all();

            //Se obtienen **todos** los servicios de la clínica identificada por 'clinica_id', incluyendo la relación con 'status'.
            $Lista_servicios=Servicio::with('status')->where('clinica_id',$datos['clinica_id'])->get();

            //Se inicializa una nueva consulta (query) sobre los servicios de la clínica.
            $query=Servicio::query()->with('status')->where('clinica_id',$datos['clinica_id']);

            $filtrobusqueda = [];

            //Aplicación del Filtro por Servicio
            if ($request->filled('servicio')) {
                $query->where('id', '=', $request->servicio); 
                $servicio = Servicio::find($request->servicio);
                $filtrobusqueda[] = "Servicio: " . ($servicio ? $servicio->descripcion : "Desconocido");
            }

            //Aplicación del Filtro por Estado (Status)
            if($request->filled('estado')){
                $query->where('status_id', $request->estado);
                $filtrobusqueda[]="Estado: ".ucfirst($request->estado);
            }

            //Ejecución de la Consulta Filtrada
            $servicios = $query->get();

            //Creación del Mensaje de Resultado
            $resultado = count($servicios) > 0
                ? "Se encontraron: " . count($servicios) . " servicios"
                : "No se encontraron resultados";

            if (!empty($filtrobusqueda)) {
                $resultado .= " para " . implode(", ", $filtrobusqueda);
            }

            //Retorno de Respuesta Exitosa
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'puestos',
                    'servicios',
                    'Lista_servicios',  
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

//---------------------------Profesiones--------------------------------------------------

/**
 * Obtiene un listado de especialidades/profesiones registradas para la clínica del usuario,
 * permitiendo filtrar los resultados por especialidad específica y estado (activo/inactivo).
 *
 * También devuelve una lista completa de especialidades ('Lista_especialidades')
 * para ser usada en los selectores de filtrado en la vista.
 *
 * @param \Illuminate\Http\Request $request La solicitud HTTP que puede contener parámetros de filtro (especialidad, estado).
 * @param int $usuario_id El ID del usuario actual para determinar la clínica.
 * @return \Illuminate\Http\JsonResponse Retorna una respuesta JSON con 'success' y los datos
 * (especialidades filtradas, lista completa de especialidades, resultado del filtro) en caso de éxito,
 * o un mensaje de error con código 500 en caso de fallo.
 */
    public function index_profesiones(Request $request,int $usuario_id){

        try{
            //Obtener datos clave del usuario
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            //Obtener la lista completa de especialidades/profesiones de la clínica (para el selector de filtro).
            $Lista_especialidades=Especialidad::where('clinica_id',$datos['clinica_id'])->get();

            // Iniciar la consulta base para obtener las especialidades.
            $query=Especialidad::query()->with('status')
                ->where('clinica_id',$datos['clinica_id']);

            $filtrobusqueda = [];// Array para almacenar los filtros aplicados

            // Filtra por el ID de una especialidad específica
            if ($request->filled('especialidad')) {
                $query->where('id', '=', $request->especialidad); 
                $especialidad = Especialidad::find($request->especialidad);
                $filtrobusqueda[] = "Profesión: " . ($especialidad ? $especialidad->descripcion : "Desconocido");
            }

            // Filtra por el status_id (ej. activo/inactivo)
            if($request->filled('estado')){
                $query->where('status_id', $request->estado);
                $filtrobusqueda[]="Estado: ".ucfirst($request->estado);
            }

            //Ejecutar la consulta final y obtener las especialidades filtradas.
            $especialidades = $query->get();

            // 4. Preparar el mensaje de resultado del filtro.
            $resultado = count($especialidades) > 0
                ? "Se encontraron: " . count($especialidades) . " servicios"
                : "No se encontraron resultados";

            if (!empty($filtrobusqueda)) {
                $resultado .= " para " . implode(", ", $filtrobusqueda);
            }

            // Retornar la respuesta JSON con todos los datos.
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'especialidades',
                    'Lista_especialidades',
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

//----------------------------------Citas------------------------------------------------

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

    public function index_citas(Request $request,int $usuario_id){

        try{
            //Obtener datos clave del usuario
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            $personal_medico=Personal::whereHas('usuario',function($q) use($datos){
                $q->where('clinica_id',$datos['clinica_id']);
            })->where('puesto_id','!=',1)->get();

            $pacientes=pacientes::where('clinica_id',$datos['clinica_id'])->get();
            
            $query=citas::query()->with(['personal','paciente','status'])
            ->whereHas('personal.usuario',function($q) use($datos){
                $q->where('clinica_id',$datos['clinica_id']);
            })->orderBy('fecha_cita','asc');  

            $filtrobusqueda=[];

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

            $citas = $query->get();

            $resultado=count($citas)>0 ? "Se encontraron: ".count($citas)." citas":"No se encontraron resultado";
            if(!empty($filtrobusqueda)){
                $resultado.=" para ".implode(", ",$filtrobusqueda);
            }

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
            //    b) Tiene un puesto_id = 2 (medico) o administrador.
            $personal=Personal::whereHas('usuario',function($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id)
                    ->where('status_id',1);
            })->where('puesto_id','!=',1)->get();

            //Obtener todos los servicios ofrecidos por la clínica específica.
            $servicios=Servicio::where('clinica_id',$clinica_id)
               ->where('status_id',1)->get();

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

    //  /**
    //  *  detalleCita se encuentra en adminController-adminPanel/DetalleCita
    //  */
    
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
    public function index_calendario(int $usuario_id){
        try{
            $datos=$this->usuarioService->DatosUsuario($usuario_id);

            $citas=Citas::with(['personal.usuario','paciente','servicio','status'])
                ->wherehas('personal.usuario',function($q) use($datos){
                    $q->where('clinica_id',$datos['clinica_id']);
                })
                ->get()
                ->map(function ($cita){
                    return[
                        'id' => $cita->id,
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
                        'status' => $cita->status->descripcion ?? null, 
                        'tipocita' => $cita->tipocita->id ?? null  
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

}
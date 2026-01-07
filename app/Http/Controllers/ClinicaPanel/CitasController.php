<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use App\Models\Disponibilidad;
use App\Services\NotificacionService;
use App\Models\Expedientes;
use App\Models\Servicio;
use App\Services\PlanService;
use App\Services\CitaService;
use App\Services\UsuarioService;
use App\Services\GoogleService;
use Exception;
use Carbon\Carbon;
use App\Models\Citas;
use Illuminate\Http\Request;

/**
 * Controlador encargado de gestionar el ciclo de vida de las citas médicas:
 * creación, finalización y cancelación. 
 * Se comunica con el servicio de notificaciones para alertar a los médicos.
 */

class CitasController extends Controller
{

     /**
     * Servicio encargado de manejar notificaciones relacionadas a citas.
     *
     * @var NotificacionService
     */
    protected $notificacionService;
    protected $usuarioService;
    protected $planService;
    protected $citaService;

    protected $googleService;


     /**
     * Constructor que inyecta el servicio de notificaciones.
     *
     * @param NotificacionService $notificacionService
     */

    public function __construct(NotificacionService $notificacionService, UsuarioService $usuarioService, 
        PlanService $planService,CitaService $citaService, GoogleService $googleService ){
        $this->notificacionService=$notificacionService;
        $this->usuarioService=$usuarioService;
        $this->planService =$planService;
        $this->citaService=$citaService;
        $this->googleService=$googleService;
    }

/**
 * Registra una o varias citas médicas (simples o recurrentes).
 *
 * Proceso:
 * - Obtiene los datos del usuario y la clínica.
 * - Valida los datos de entrada de la cita.
 * - Verifica si el plan contratado permite crear nuevas citas.
 * - Calcula la hora de finalización de la cita según la duración del servicio.
 * - Valida la disponibilidad del médico en el horario seleccionado.
 * - Crea la cita o citas recurrentes según los parámetros recibidos.
 *
 * @param \Illuminate\Http\Request $request
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error en el proceso.
 *
 * @throws \Exception
 */
    public function store(Request $request)
    {
        try {

            // Obtener datos del usuario y clínica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Validación de los campos de entrada
            $validated = $request->validate([
                'medico' => 'required|integer',
                'paciente' => 'required|integer',
                'servicio' => 'required|integer',
                'fecha' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i'
            ]);

            // Validar límite de citas según el plan
            if (!$this->planService->puedeCrearCita($datos['clinica_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de citas alcanzadas',
                    'error'=> 'LIMITE_CITAS'
                ], 404);   
            }

            // Calcular hora de inicio y fin de la cita
            $duracionServicio = Servicio::find($validated['servicio'])->duracion;
            $hora_inicio = Carbon::createFromFormat('H:i', $validated['hora_inicio']);
            $hora_fin = $hora_inicio->copy()->addMinutes($duracionServicio);

            //checar disponibilidad del medico en la fecha elegida
            $this->usuarioService->personal_citas($request->all(),$hora_inicio,$hora_fin);

            // Crear cita(s) simples o recurrentes
            $this->citaService->crearCitasRecurrentes($validated,$hora_inicio,
                $hora_fin,$request->input('repetir'), $request->input('repeticiones'),$request->usuario_id);
            

            return response()->json([
                'success' => true,
                'message' => 'Cita Creada con Exito.',
            ]);
           

        } catch (Exception $e) {
             return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);  
        }
    }

    public function store_citarapida(Request $request)
    {
        try {

            // Obtener datos del usuario y clínica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Validación de los campos de entrada
            $validated = $request->validate([
                'medico' => 'required|integer',
                'paciente' => 'required|integer',
                'servicio' => 'required|integer',
                'fecha' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i',
                'resultados'=>'required|string',
            ]);

            // Validar límite de citas según el plan
            if (!$this->planService->puedeCrearCita($datos['clinica_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de citas alcanzadas',
                    'error'=> 'LIMITE_CITAS'
                ], 404);   
            }

            // Calcular hora de inicio y fin de la cita
            $duracionServicio = Servicio::find($validated['servicio'])->duracion;
            $hora_inicio = Carbon::createFromFormat('H:i', $validated['hora_inicio']);
            $hora_fin = $hora_inicio->copy()->addMinutes($duracionServicio);

            $cita = citas::create([
                'tipocita_id'=>2,
                'personal_id' => $validated['medico'],
                'paciente_id' => $validated['paciente'],
                'servicio_id' => $validated['servicio'],
                'fecha_cita'  => $validated['fecha'],
                'hora_inicio' => $hora_inicio->format('H:i'),
                'hora_fin'    => $hora_fin->format('H:i'),
                'status_id'   => 3,
                'created_at'  => now(),
                'updated_at'  => now()
            ]);

             //crear el expediente clinico
            Expedientes::create([
                'cita_id'=>$cita->id,
                'motivo_consulta'=>$validated['resultados'],
                // 'objetivo'=>null,
                // 'proceso'=>null,
                // 'resultados'=>$validated['resultados'],
                'fecha'=>now()->toDateString()
            ]);
            

            return response()->json([
                'success' => true,
                'message' => 'Cita Rapida Creada con Exito.',
            ]);
           

        } catch (Exception $e) {
             return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);  
        }
    }

/**
 * Obtiene la disponibilidad de un médico para una fecha específica.
 *
 * Este método:
 * - Obtiene la disponibilidad configurada del médico.
 * - Obtiene las citas ya agendadas para la fecha solicitada.
 * - Determina si el médico:
 *      - No tiene disponibilidad configurada
 *      - Tiene disponibilidad, pero no para ese día
 *      - Tiene disponibilidad para ese día
 *
 * @param \Illuminate\Http\Request $request
 *        Request que contiene la fecha a consultar (query param: fecha).
 *
 * @param int $medico_id
 *        ID del médico (personal) del cual se consultará la disponibilidad.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON con la disponibilidad y citas del médico.
 *
 * @throws \Exception
 */
    public function disponibilidad(Request $request,int $medico_id)
    { 
        try{
            // Fecha solicitada (YYYY-MM-DD)
            $fecha= $request->query('fecha');

            // Obtener disponibilidad general del médico
            $disponibilidad_medico=Disponibilidad::where('personal_id',$medico_id)->get();

            //buscar si hay citas agendadas para ese dia 
            $medico_citas=Citas::where('personal_id',$medico_id)
            ->whereDate('fecha_cita',$fecha)
            ->where('status_id',1)
            ->get(['hora_inicio','hora_fin']);

            // Si el médico no tiene disponibilidad configurada
            if($disponibilidad_medico->isEmpty()){
                return response()->json([
                    'medico_citas'=>$medico_citas,
                    'disponibilidad'=>'sin_configurar'
                ]);
            }

            // Obtener día de la semana en español
            $diaSemana=Carbon::parse($fecha)->locale('es')->dayName;
            $diaSemana=strtolower($diaSemana);

            //buscar disponibilidad del medico
            $disponibilidad_dia=Disponibilidad::where('personal_id',$medico_id)
                ->where('dia',$diaSemana)->first();
            
            // El médico tiene disponibilidad, pero no ese día
            if (!$disponibilidad_dia) {
                return response()->json([
                    'medico_citas' => $medico_citas,
                    'disponibilidad' => 'no_disponible' // tiene disponibilidad, pero no para ese día
                ]);
            }

            // El médico tiene disponibilidad para el día
            return response()->json([
                'medico_citas'=>$medico_citas,
                'disponibilidad'=>$disponibilidad_dia
            ]);
                  
        }catch (Exception $e) {
             return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);  
        }
     
    }


/**
 * Finaliza una cita médica y genera su expediente clínico.
 *
 * Este método:
 * - Valida la información clínica de cierre de la cita.
 * - Actualiza el estado de la cita a finalizada.
 * - Notifica al médico sobre la finalización de la cita.
 * - Crea el expediente clínico asociado a la cita.
 *
 * @param \Illuminate\Http\Request $request
 *
 * @param int $cita_id
 *        ID de la cita a finalizar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error.
 *
 * @throws \Exception
 */
    public function update(Request $request,int $cita_id){
       
        try{
            //validacion de entrada
            $validated=$request->validate([
                'objetivo'=>'required|string',
                'proceso'=>'required|string',
                'resultados'=>'required|string'
            ]);

            // Buscar y actualizar la cita a finalizada
            $cita=Citas::findOrfail($cita_id);
            $cita->update([
                'status_id'=>3
            ]);

            //notificar al medico
            $this->notificacionService->finalizar_cita($cita->personal_id,$cita_id);
    
            //crear el expediente clinico
            Expedientes::create([
                'cita_id'=>$cita_id,
                'objetivo'=>$validated['objetivo'],
                'proceso'=>$validated['proceso'],
                'resultados'=>$validated['resultados'],
                'fecha'=>now()->toDateString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cita finalizada con Exito.',
            ]);

        }catch (Exception $e) {
             return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);  
        }
       
    }
 
/**
 * Cancela una cita médica y notifica a los involucrados.
 *
 * Este método:
 * - Busca la cita por su ID.
 * - Actualiza el estado de la cita a cancelada.
 * - Notifica al médico sobre la cancelación.
 * - Si la cita está sincronizada con Google Calendar,
 *   elimina el evento correspondiente.
 *
 * @param int $cita_id
 *        ID de la cita que se desea cancelar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error en el proceso.
 *
 * @throws \Exception
 */
    public function cancelar(int $cita_id){
        try{

            // Buscar y actualizar la cita
            $cita=citas::find($cita_id);

            $cita->update([
                'status_id'=>4
            ]);

            // Notificar al médico
            $this->notificacionService->cancelar_cita($cita->personal_id,$cita_id);
            if($cita->google_owner_id){
                $usuarioCreador=$this->googleService->UsuarioCreador($cita->personal_id,$cita->personal->usuario->clinicas->id);
                $this->googleService->eliminarEvento($cita->event_google_id,$usuarioCreador);
            }
           

            return response()->json([
                'success' => true,
                'message' => 'Cita cancelada con Exito.',
            ]);
            
        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }
/**
 * Genera y devuelve el reporte de citas de una clínica con sus gráficas.
 *
 * Este método:
 * - Obtiene los datos del usuario y la clínica.
 * - Genera el reporte estadístico de citas según los filtros enviados.
 * - Genera las gráficas correspondientes al reporte.
 * - Retorna la información consolidada en formato JSON.
 *
 * @param \Illuminate\Http\Request $request
 *        Request que contiene el ID del usuario y filtros de fecha.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON con los datos del reporte y las gráficas.
 */
    public function DescargarReporteCitas(Request $request){

        try{
            // Obtener datos del usuario y clínica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Generar reporte de citas
            $reporte=$this->citaService->reporteCitas($datos,$request->all());

            // Generar gráficas del reporte
            $graficas=$this->citaService->graficasCitas($reporte);     

            return response()->json([
                'success'=>true,
                'data'=>array_merge(
                    $reporte,$graficas
                )
            ]);
        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
       
        
    }
}

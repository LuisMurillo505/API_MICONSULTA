<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use App\Models\Disponibilidad;
use App\Services\NotificacionService;
use App\Models\Expedientes;
use App\Models\Servicio;
use App\Services\PlanService;
use Barryvdh\DomPDF\Facade\Pdf;
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
     * Almacena una nueva cita médica en la base de datos.
     * 
     * Valida los datos enviados por el formulario y, si son válidos, 
     * crea una nueva cita activa y notifica al médico correspondiente.
     */
    public function store(Request $request)
    {
        try {

            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Validación de los campos de entrada
            $validated = $request->validate([
                'medico' => 'required|integer',
                'paciente' => 'required|integer',
                'servicio' => 'required|integer',
                'fecha' => 'required|date',
                'hora_inicio' => 'required|date_format:H:i'
            ]);

            //checar citas permitidos

            if (!$this->planService->puedeCrearCita($datos['clinica_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de citas alcanzadas',
                    'error'=> 'LIMITE_CITAS'
                ], 404);   
            }

            // Calcular hora fin de la cita
            $duracionServicio = Servicio::find($validated['servicio'])->duracion;
            $hora_inicio = Carbon::createFromFormat('H:i', $validated['hora_inicio']);
            $hora_fin = $hora_inicio->copy()->addMinutes($duracionServicio);

            //checar disponibilidad del medico en la fecha elegida
            $this->usuarioService->personal_citas($request->all(),$hora_inicio,$hora_fin);

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

    public function disponibilidad(Request $request,int $medico_id)
    { 
        try{
            $fecha= $request->query('fecha');

            $disponibilidad_medico=Disponibilidad::where('personal_id',$medico_id)->get();

             //buscar si hay citas agendadas para ese dia 
            $medico_citas=Citas::where('personal_id',$medico_id)
            ->whereDate('fecha_cita',$fecha)
            ->where('status_id',1)
            ->get(['hora_inicio','hora_fin']);

            if($disponibilidad_medico->isEmpty()){
                return response()->json([
                    'medico_citas'=>$medico_citas,
                    'disponibilidad'=>'sin_configurar'
                ]);
            }else{
                $diaSemana=Carbon::parse($fecha)->locale('es')->dayName;
                $diaSemana=strtolower($diaSemana);

                //buscar disponibilidad del medico
                $disponibilidad_dia=Disponibilidad::where('personal_id',$medico_id)
                    ->where('dia',$diaSemana)->first();
                
                     if (!$disponibilidad_dia) {
                        return response()->json([
                            'medico_citas' => $medico_citas,
                            'disponibilidad' => 'no_disponible' // tiene disponibilidad, pero no para ese día
                        ]);
                    }

                return response()->json([
                    'medico_citas'=>$medico_citas,
                    'disponibilidad'=>$disponibilidad_dia
                ]);
            }       
        }catch (Exception $e) {
             return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);  
        }
     
    }


    /**
     * Finaliza una cita existente y registra la información en el expediente médico.
     * 
     * Cambia el estado de la cita a "Finalizada", guarda el objetivo, proceso 
     * y resultados en la tabla `expedientes`, y notifica al médico.
     */
    public function update(Request $request,int $cita_id){
       
        try{
            //validacion de entrada
            $validated=$request->validate([
                'objetivo'=>'required|string',
                'proceso'=>'required|string',
                'resultados'=>'required|string'
            ]);

            //busca y actualiza la cita
            $cita=Citas::findOrfail($cita_id);
            $cita->update([
                'status_id'=>3
            ]);

            //notificar al medico
            $this->notificacionService->finalizar_cita($cita->personal_id,$cita_id);
    
            //crear el expediente clinico
            Expedientes::create([
                'personal_id'=>$cita->personal_id,
                'paciente_id'=>$cita->paciente_id,
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
     * Cancela una cita médica existente y notifica al médico.
     * 
     * Cambia el estado de la cita a "Cancelada" y genera la notificación correspondiente.
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

    public function DescargarReporteCitas(Request $request){

        $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

        $reporte=$this->citaService->reporteCitas($datos,$request->all());
        $graficas=$this->citaService->graficasCitas($reporte);     


        $fechaNombre= Carbon::parse($reporte['fechaInicio'])->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        return response()->json([
            'success'=>true,
            'data'=>array_merge(
                $reporte,$graficas
            )
        ]);
        
    }
}

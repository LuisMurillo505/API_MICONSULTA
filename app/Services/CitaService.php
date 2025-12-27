<?php

namespace App\Services;

use App\Models\Clinicas;
use ArrayAccess;
use Illuminate\Support\Facades\Http;
use Exception;
use Carbon\Carbon;
use App\Models\Citas;
use App\Models\Servicio;
use App\Models\Personal;
use App\Models\Usuario;
use App\Models\Pacientes;
use Illuminate\Support\Facades\Mail;
use App\Models\Disponibilidad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class CitaService
{
    protected $notificacionService;
    protected $usuarioService;
    protected $googleService;
    protected $planServices;

    public function __construct(NotificacionService $notificacionService, 
        usuarioService $usuarioService, GoogleService $googleService,
        PlanService $planServices)
    {
        $this->notificacionService = $notificacionService;
        $this->usuarioService = $usuarioService;
        $this->googleService = $googleService;
        $this->planServices = $planServices;
    }

/**
 * Verifica si existe un conflicto de horario para una cita médica.
 *
 * Este método valida si el personal indicado ya tiene una cita activa
 * que se solape con el rango de horas proporcionado en la fecha indicada.
 *
 * Se considera conflicto cuando:
 * - La cita existente inicia antes de la hora fin solicitada
 * - Y finaliza después de la hora inicio solicitada
 *
 * @param int $personal_id
 *        ID del personal (médico) al que se le desea validar disponibilidad.
 *
 * @param \Carbon\Carbon $fecha
 *        Fecha en la que se desea agendar la cita.
 *
 * @param \Carbon\Carbon $hora_inicio
 *        Hora de inicio de la nueva cita.
 *
 * @param \Carbon\Carbon $hora_fin
 *        Hora de fin de la nueva cita.
 *
 * @return bool
 *         Retorna true si existe conflicto de horario,
 *         false si el horario está disponible.
 */
    public function conflictoCita(int $personal_id, Carbon $fecha,Carbon $hora_inicio, Carbon $hora_fin):bool{

        // Verificar solapamiento con otras citas
        return citas::where('personal_id', $personal_id)
            ->where('status_id',1)
            ->whereDate('fecha_cita', $fecha->toDateString())
            ->where(function ($q) use ($hora_inicio, $hora_fin) {
                $q->where(function ($q2) use ($hora_inicio, $hora_fin) {
                    $q2->where('hora_inicio', '<', $hora_fin->format('H:i'))
                    ->where('hora_fin', '>', $hora_inicio->format('H:i'));
                });
            })->exists();
    }

/**
 * Crea citas médicas recurrentes y, opcionalmente, eventos en Google Calendar.
 *
 * Este método permite generar múltiples citas a partir de una fecha inicial,
 * repitiéndolas según el patrón indicado (diario, semanal, etc.).
 * Valida disponibilidad del médico, evita conflictos de horario y maneja
 * transacciones para garantizar consistencia de datos.
 *
 * Si ocurre un error (conflicto de cita, falta de disponibilidad o error externo),
 * se eliminan las citas creadas y los eventos de Google Calendar asociados.
 *
 * @param array|null  $data            Datos de la cita:
 *                                     - medico (int) ID del médico
 *                                     - paciente (int) ID del paciente
 *                                     - servicio (int) ID del servicio
 *                                     - fecha (string) Fecha inicial (Y-m-d)
 *                                     - hora_inicio (string) Hora de inicio (H:i)
 *
 * @param Carbon      $hora_inicio      Hora de inicio base de la cita
 * @param Carbon      $hora_fin         Hora de fin base de la cita
 * @param string|null $repetir          Tipo de recurrencia (diaria, semanal, mensual, etc.)
 * @param int|null    $repeticiones     Número de veces que se repetirá la cita
 * @param int         $usuario_id       ID del usuario que crea las citas
 *
 * @return array                       Arreglo con los IDs de las citas creadas
 *
 * @throws \Exception                  Si el médico no tiene disponibilidad
 *                                     o existe un conflicto con otra cita
 */
    public function crearCitasRecurrentes(?array $data, Carbon $hora_inicio, Carbon $hora_fin, ?string $repetir, ?int $repeticiones,int $usuario_id ) :array{
        DB::beginTransaction();
        try{
            // Obtener datos del usuario y clínica
            $datos=$this->usuarioService->DatosUsuario($usuario_id);
            $citasCreadas = [];
            $eventosCreados=[];

            // Asegurar al menos una repetición
            $repeticiones = max(1, (int) $repeticiones);
            $fechaInicial = Carbon::parse($data['fecha']);

            // Validar disponibilidad del médico una sola vez si existe
            $disponibilidadMedico=Disponibilidad::where('personal_id',$data['medico'])->get();

            // Obtener duración del servicio
            $duracionServicio = Servicio::find($data['servicio'])->duracion;

             // Verificar si puede usar Google Calendar
            $puedeUsargoogleCalendar=$this->planServices->puedeUsarGoogleCalendar($datos['clinica_id']);
            $usuarioCreador=null;

            if ($puedeUsargoogleCalendar) {
                $usuarioCreador = $this->googleService->usuarioCreador($data['medico'], $datos['clinica_id']);
            }
          
            // Crear citas según el número de repeticiones
            for ($i = 0; $i < $repeticiones; $i++) {

               $fechaActual = $this->calcularFechaRecurrente($fechaInicial, $repetir, $i);

                if (!$fechaActual) continue;

                // Validar disponibilidad del médico
                if($disponibilidadMedico->isNotEmpty()){
                    if (!$this->usuarioService->disponibilidad_dia($data['medico'], $fechaActual, $hora_inicio, $hora_fin)) {

                        $this->eliminarEventoGoogle($eventosCreados,$citasCreadas, $usuarioCreador);

                        throw new Exception("El médico no tiene disponibilidad el día " . $fechaActual->locale('es')->isoFormat('D [de] MMMM [de] YYYY'));
                    }
                }
               
                // Calcular horas de la cita
                $hora_inicio_actual = Carbon::createFromFormat('H:i', $data['hora_inicio']);
                $hora_fin_actual = $hora_inicio_actual->copy()->addMinutes($duracionServicio);

                // Validar conflicto con otras citas
                if ($this->conflictoCita($data['medico'], $fechaActual, $hora_inicio, $hora_fin)) {

                    $this->eliminarEventoGoogle($eventosCreados,$citasCreadas, $usuarioCreador);

                    throw new Exception("El médico ya tiene una cita agendada el día " . $fechaActual->locale('es')->isoFormat('D [de] MMMM [de] YYYY').
                        ' a las '.$hora_inicio_actual->format('g:i a'));
                }

                // Crear cita
                $cita = citas::create([
                    'personal_id' => $data['medico'],
                    'paciente_id' => $data['paciente'],
                    'servicio_id' => $data['servicio'],
                    'fecha_cita'  => $fechaActual->toDateString(),
                    'hora_inicio' => $hora_inicio_actual->format('H:i'),
                    'hora_fin'    => $hora_fin_actual->format('H:i'),
                    'status_id'   => 1,
                    'created_at'  => now(),
                    'updated_at'  => now()
                ]);

                $citasCreadas[] = $cita->id;

                 // Crear evento en Google Calendar
                if ($puedeUsargoogleCalendar && $usuarioCreador['usuarioCreador']) {
                    $evento=$this->googleService->crearEventoGoogleCalendar($cita,$usuarioCreador,
                            Carbon::parse($fechaActual)->toDateString(),$hora_inicio_actual->format('H:i'),$hora_fin_actual->format('H:i'));
                    
                    if ($evento) {
                        $eventosCreados[] = $evento->id;
                    }
                }
              
                //notificacion sistema
                $this->notificacionService->crear_cita($data['medico'], $cita->id);
        
            }

            DB::commit();

            return $citasCreadas;

        }catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
   }

/**
 * Calcula la fecha correspondiente a una cita recurrente según el tipo de repetición.
 *
 * A partir de una fecha inicial, determina la nueva fecha sumando días, semanas
 * o meses dependiendo del patrón de repetición seleccionado.
 *
 * @param Carbon      $fechaInicial Fecha base desde la cual se calculan las repeticiones.
 * @param string|null $repetir     
 * @param int         $indice       Índice de la repetición (0 para la primera cita).
 *
 * @return Carbon|null Devuelve la fecha calculada para la repetición indicada
 *                     o null si el tipo de repetición no es válido y no es la primera.
 */
    private function calcularFechaRecurrente(Carbon $fechaInicial, ?string $repetir, int $indice): ?Carbon
    {
        return match($repetir) {
            'diaria'    => $fechaInicial->copy()->addDays($indice),
            'cada3'     => $fechaInicial->copy()->addDays($indice * 3),
            'semanal'   => $fechaInicial->copy()->addWeeks($indice),
            'quincenal' => $fechaInicial->copy()->addWeeks($indice * 2),
            'mensual'   => $fechaInicial->copy()->addMonths($indice),
            default     => $indice === 0 ? $fechaInicial->copy() : null,
        };
    }

/**
 * Elimina eventos previamente creados en Google Calendar y borra las citas asociadas en la base de datos.
 *
 * Este método se utiliza como mecanismo de rollback cuando ocurre un error
 * durante la creación de citas recurrentes. Garantiza que no queden eventos
 * huérfanos en Google Calendar ni registros inconsistentes en la base de datos.
 *
 * @param array $eventosCreados  Arreglo de IDs de eventos creados en Google Calendar.
 * @param array $citasCreadas    Arreglo de IDs de citas creadas en la base de datos.
 * @param array $usuarioCreador  Información del usuario autenticado en Google Calendar
 *                               necesario para eliminar los eventos.
 *
 * @return void
 */
    private function eliminarEventoGoogle(array $eventosCreados, array $citasCreadas, array $usuarioCreador){

        try{
            foreach ($eventosCreados as $eventoId) {
                $this->googleService->eliminarEvento($eventoId, $usuarioCreador);
            }
            Citas::whereIn('id', $citasCreadas)->delete();
        }catch(Exception $e) {
            throw $e;
        }
       
    }

/**
 * Genera un reporte estadístico de citas de una clínica dentro de un rango de fechas.
 *
 * El rango de fechas puede definirse de las siguientes formas (prioridad en orden):
 * 1. Por mes específico (YYYY-MM) usando `fecha_mes`
 * 2. Por semana específica (YYYY-Wxx) usando `fecha_semana`
 * 3. Por rango de fechas usando `fecha_inicio` y `fecha_fin`
 * 4. Si no se envía ningún filtro, se toma el día actual por defecto
 *
 * El reporte incluye:
 * - Total de citas finalizadas
 * - Total de citas canceladas
 * - Porcentajes de finalizadas y canceladas
 * - Citas agrupadas por paciente con porcentaje
 * - Paciente con más citas
 * - Citas agrupadas por médico con porcentaje
 * - Médico con más citas
 *
 * @param array $datos  Información del usuario autenticado (incluye clinica_id)
 * @param array $request Parámetros de filtro:
 *                       - fecha_mes (YYYY-MM)
 *                       - fecha_semana (YYYY-Wxx)
 *                       - fecha_inicio (YYYY-MM-DD)
 *                       - fecha_fin (YYYY-MM-DD)
 *
 * @return array
 */
    public function reporteCitas(array $datos,array $request)
    {
        
        // Variables de rango
        $fechaInicio = null;
        $fechaFin = null;

        // Caso 1: Filtro por mes (YYYY-MM)
        if (!empty($request['fecha_mes'])) {
            [$anio, $mes] = explode('-', $request['fecha_mes']);
            $fechaInicio = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
            $fechaFin = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();
        }

        // Caso 2: Filtro por semana (YYYY-Wxx)
        elseif (!empty($request['fecha_semana'])) {
            // La fecha_semana viene como "2025-W33"
            $fechaInicio = Carbon::parse($request['fecha_semana'])->startOfWeek();
            $fechaFin = Carbon::parse($request['fecha_semana'])->endOfWeek();
        }

        // Caso 3: Rango de fechas
        elseif (!empty($request['fecha_inicio']) && !empty($request['fecha_fin'])) {
            $fechaInicio = Carbon::parse($request['fecha_inicio']);
            $fechaFin = Carbon::parse($request['fecha_fin']);
        }

        // Si no se seleccionó nada -> mes actual por default
        else {
           $fechaInicio = now()->startOfDay();
           $fechaFin = now()->endOfDay();
        }

        // Consultas reutilizando el rango
        $CitasFinalizadas = Citas::whereHas('personal.usuario', function ($q) use ($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })
            ->where('status_id', 3)
            ->whereBetween('fecha_cita', [$fechaInicio, $fechaFin])
            ->count();

        $CitasCanceladas = Citas::whereHas('personal.usuario', function ($q) use ($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })
            ->where('status_id', 4)
            ->whereBetween('fecha_cita', [$fechaInicio, $fechaFin])
            ->count();

        //porcentajes
        $total= $CitasFinalizadas+$CitasCanceladas;
        $porcentajeFinalizadas=$total>0 ? round(($CitasFinalizadas/$total)*100,2) : 0;
        $porcentajeCanceladas=$total>0 ? round(($CitasCanceladas/$total)*100,2) : 0;

        $CitasPaciente = Citas::whereHas('personal.usuario', function ($q) use ($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })
            ->whereHas('paciente')
            ->where('status_id', 3)
            ->whereBetween('fecha_cita', [$fechaInicio, $fechaFin])
            ->select('paciente_id', DB::raw('count(id) as citas_total'))
            ->groupBy('paciente_id')
            ->orderBy('citas_total','desc')
            ->with('paciente')
            ->get();

        //agregar porcentaje de cada paciente
        $CitasPaciente->transform(function ($item) use ($CitasFinalizadas) {
            $item->porcentaje = $CitasFinalizadas > 0
                ? round(($item->citas_total / $CitasFinalizadas) * 100, 2)
                : 0;
            return $item;
        });

        //paciente mas consultado
        $pacienteConMasCitas = $CitasPaciente->sortByDesc('citas_total')->first();


        $CitasMedico = Citas::whereHas('personal.usuario', function ($q) use ($datos) {
            $q->where('clinica_id', $datos['clinica_id']);
        })
        ->where('status_id', 3)
        ->whereBetween('fecha_cita', [$fechaInicio, $fechaFin])
        ->select('personal_id', DB::raw('count(id) as citas_total'))
        ->groupBy('personal_id')
        ->orderBy('citas_total','desc')
        ->with('personal')
        ->get();

        //agregar porcentaje de cada medico
        $CitasMedico->transform(function ($item) use ($CitasFinalizadas) {
            $item->porcentaje = $CitasFinalizadas > 0
                ? round(($item->citas_total / $CitasFinalizadas) * 100, 2)
                : 0;
            return $item;
        });

        //medico con mas citas
        $MedicoConMasCitas = $CitasMedico->sortByDesc('citas_total')->first();


        return [
            'nombre_reporte' => 'Reporte de citas',
            'citas_finalizadas' => $CitasFinalizadas ?? 0,
            'citas_canceladas' => $CitasCanceladas ?? 0,
            'porcentaje_finalizadas' => $porcentajeFinalizadas ?? 0,
            'porcentaje_canceladas' => $porcentajeCanceladas ?? 0,
            'citas_pacientes' => $CitasPaciente ?? null,
            'pacienteConMasCitas'=>$pacienteConMasCitas ?? null,
            'citas_medico' => $CitasMedico ?? null,
            'MedicoConMasCitas'=>$MedicoConMasCitas ?? null,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin
        ];

    }

/**
 * Genera las gráficas estadísticas de citas en formato imagen base64
 * utilizando QuickChart.
 *
 * Se generan tres gráficas:
 * 1. Citas finalizadas vs canceladas.
 * 2. Citas finalizadas por paciente.
 * 3. Citas finalizadas por médico.
 *
 * @param array $reporte  Arreglo con los datos del reporte de citas.
 *                        Debe contener:
 *                        - citas_finalizadas (int)
 *                        - citas_canceladas (int)
 *                        - citas_pacientes (Collection)
 *                        - citas_medico (Collection)
 *
 * @return array Retorna un arreglo con las imágenes base64 de las gráficas:
 *               - chartCitas
 *               - chartPacientes
 *               - chartMedico
 */
    public function graficasCitas(array $reporte)
    {

        // Colores reutilizables para las gráficas dinámicas
        $coloresDisponibles = [
            "#198754", "#0d6efd", "#ffc107", "#dc3545", "#6f42c1",
            "#20c997", "#fd7e14", "#6610f2", "#0dcaf0", "#adb5bd"
        ];

         /*
        |--------------------------------------------------------------------------
        | Gráfica 1: Citas finalizadas vs canceladas
        |--------------------------------------------------------------------------
        */
        $chartCitasConfig = [
            "type" => "doughnut",
            "data" => [
                "labels" => ["Finalizadas", "Canceladas"],
                "datasets" => [[
                    "data" => [$reporte['citas_finalizadas'], $reporte['citas_canceladas']],
                    "backgroundColor" => ["#0d6efd", "#dc3545"]
                ]]
            ],
            "options" => [
                "plugins" => [
                    "legend" => ["position" => "bottom"]
                ]
            ]
        ];

        // Generar imagen base64 desde QuickChart
        $chartCitasUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartCitasConfig));
        $chartCitasImage = base64_encode(file_get_contents($chartCitasUrl));
        $chartCitas = "data:image/png;base64," . $chartCitasImage;

         /*
        |--------------------------------------------------------------------------
        | Gráfica 2: Citas finalizadas por paciente
        |--------------------------------------------------------------------------
        */
        $labels = [];
        $data = [];
        $colores = [];

        foreach ($reporte['citas_pacientes'] as $index => $item) {
            $labels[] = $item->paciente->nombre; // o el campo correspondiente (nombre_completo, etc.)
            $data[] = $item->citas_total;
            $colores[] = $coloresDisponibles[$index % count($coloresDisponibles)];
        }
           $chartPacientesConfig = [
            "type" => "doughnut",
            "data" => [
                "labels" => $labels,
                "datasets" => [[
                    "data" => $data,
                    "backgroundColor" => $colores
                ]]
            ],
             "options" => [
                "responsive" => true,
                "plugins" => [
                    "legend" => ["display" => false],
                    "title" => [
                        "display" => true,
                        "text" => "Citas finalizadas por paciente"
                    ]
                ],
                "scales" => [
                    "y" => [
                        "beginAtZero" => true,
                        "precision" => 0
                    ]
                ]
            ]
        ];
        $chartPacientesUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartPacientesConfig));
        $chartPacientesImage = base64_encode(file_get_contents($chartPacientesUrl));
        $chartPacientes = "data:image/png;base64," . $chartPacientesImage;

        /*
        |--------------------------------------------------------------------------
        | Gráfica 3: Citas finalizadas por médico
        |--------------------------------------------------------------------------
        */
        $labels = [];
        $data = [];
        $colores = [];

        foreach ($reporte['citas_medico'] as $index => $item) {
            $labels[] = $item->personal->nombre; // o el campo correspondiente (nombre_completo, etc.)
            $data[] = $item->citas_total;
            $colores[] = $coloresDisponibles[$index % count($coloresDisponibles)];
        }
           $chartMedicoConfig = [
            "type" => "doughnut",
            "data" => [
                "labels" => $labels,
                "datasets" => [[
                    "data" => $data,
                    "backgroundColor" => $colores
                ]]
            ],
             "options" => [
                "responsive" => true,
                "plugins" => [
                    "legend" => ["display" => false],
                    "title" => [
                        "display" => true,
                        "text" => "Citas finalizadas por paciente"
                    ]
                ],
                "scales" => [
                    "y" => [
                        "beginAtZero" => true,
                        "precision" => 0
                    ]
                ]
            ]
        ];
        $chartMedicoUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartMedicoConfig));
        $chartMedicoImage = base64_encode(file_get_contents($chartMedicoUrl));
        $chartMedico = "data:image/png;base64," . $chartMedicoImage;

        /*
        |--------------------------------------------------------------------------
        | Retorno de gráficas
        |--------------------------------------------------------------------------
        */

        return [
            'chartCitas'=>$chartCitas,
            'chartPacientes' => $chartPacientes,
            'chartMedico' => $chartMedico
        ];

    }

   
}
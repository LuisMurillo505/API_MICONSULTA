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


class CitaService
{
    protected $notificacionService;
    protected $usuarioService;
    protected $googleService;
    protected $whatsAppService;
    protected $planServices;

    public function __construct(NotificacionService $notificacionService, 
        usuarioService $usuarioService,
        PlanService $planServices)
    {
        $this->notificacionService = $notificacionService;
        $this->usuarioService = $usuarioService;
        // $this->googleService = $googleService;
        // $this->whatsAppService = $whatsappServices;
        $this->planServices = $planServices;
    }

    //Checa si hay conflicto de horarios con citas
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

//    public function crearCitasRecurrentes(?array $data, Carbon $hora_inicio, Carbon $hora_fin, ?string $repetir, ?int $repeticiones ) :array{
//         try{
//             $datos=$this->usuarioService->DatosUsuario();
//             $usuario=Usuario::find($datos['usuario_id']) ?? null;

//             $citasCreadas = [];
//             $eventosCreados=[];
//             $repeticiones = max(1, (int) $repeticiones);

//             $fechaInicial = Carbon::parse($data['fecha']);

                            
//             for ($i = 0; $i < $repeticiones; $i++) {

//                 $fechaActual = match($repetir) {
//                     'diaria'    => $fechaInicial->copy()->addDays($i),
//                     'cada3'     => $fechaInicial->copy()->addDays($i * 3),
//                     'semanal'   => $fechaInicial->copy()->addWeeks($i),
//                     'quincenal' => $fechaInicial->copy()->addWeeks($i * 2),
//                     default     => $i === 0 ? $fechaInicial->copy() : null,
//                 };

//                 if (!$fechaActual) continue;

//                 $disponibilidad_medico=Disponibilidad::where('personal_id',$data['medico'])->get();

//                 if($disponibilidad_medico->isNotEmpty()){
//                     if (!$this->usuarioService->disponibilidad_dia($data['medico'], $fechaActual, $hora_inicio, $hora_fin)) {
//                         foreach ($eventosCreados as $eventoId) {
//                             $this->googleService->eliminarEvento($eventoId, $cita);
        
//                         }
//                         Citas::whereIn('id', $citasCreadas)->delete();

//                         throw new Exception("El médico no tiene disponibilidad el día " . $fechaActual->locale('es')->isoFormat('D [de] MMMM [de] YYYY'));
//                     }
//                 }
               
//                 $duracionServicio = Servicio::find($data['servicio'])->duracion;
//                 $hora_inicio_actual = Carbon::createFromFormat('H:i', $data['hora_inicio']);
//                 $hora_fin_actual = $hora_inicio_actual->copy()->addMinutes($duracionServicio);

              
//                 if ($this->conflictoCita($data['medico'], $fechaActual, $hora_inicio, $hora_fin)) continue;

//                 // Crear cita
//                 $cita = citas::create([
//                     'personal_id' => $data['medico'],
//                     'paciente_id' => $data['paciente'],
//                     'servicio_id' => $data['servicio'],
//                     'fecha_cita'  => $fechaActual->toDateString(),
//                     'hora_inicio' => $hora_inicio_actual->format('H:i'),
//                     'hora_fin'    => $hora_fin_actual->format('H:i'),
//                     'status_id'   => 1,
//                     'created_at'  => now(),
//                     'updated_at'  => now()
//                 ]);

//                 $citasCreadas[] = $cita->id;

//                 //medico de la cita
//                 $medico=Personal::find($data['medico']);


//                 // if($usuario->clinicas->suscripcion->plan_id==3){
//                 //     $usuarioCreador=$this->googleService->usuarioCreador($cita,$usuario);
//                 //     if($usuarioCreador['usuarioCreador']){
//                 //         $eventU=$this->googleService->crearEventoGoogleCalendar($cita,$usuarioCreador,
//                 //             Carbon::parse($fechaActual)->toDateString(),$hora_inicio_actual->format('H:i'),$hora_fin_actual->format('H:i'));
//                 //         $eventosCreados[]=$eventU->id;
//                 //     }
//                 // }

//                 $googleCalendar=$this->planServices->puedeUsarGoogleCalendar($datos['clinica_id']);
//                 if($googleCalendar){
//                     $usuarioCreador=$this->googleService->usuarioCreador($cita,$datos['clinica_id']);
//                     if($usuarioCreador['usuarioCreador']){
//                         $eventU=$this->googleService->crearEventoGoogleCalendar($cita,$usuarioCreador,
//                             Carbon::parse($fechaActual)->toDateString(),$hora_inicio_actual->format('H:i'),$hora_fin_actual->format('H:i'));
//                         $eventosCreados[]=$eventU->id;
//                     }
//                 }
              
//                 //notificacion sistema
//                 $this->notificacionService->crear_cita($data['medico'], $cita->id);
        
//             }
//             return $citasCreadas;

//         }catch (Exception $e) {
//             throw $e;
//         }
//    }


    public function reporteCitas($datos,$request)
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

    public function graficasCitas($reporte){

        $coloresDisponibles = [
            "#198754", "#0d6efd", "#ffc107", "#dc3545", "#6f42c1",
            "#20c997", "#fd7e14", "#6610f2", "#0dcaf0", "#adb5bd"
        ];

        //citas finalizadas y canceladas 
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
        $chartCitasUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartCitasConfig));
        $chartCitasImage = base64_encode(file_get_contents($chartCitasUrl));
        $chartCitas = "data:image/png;base64," . $chartCitasImage;

        //citas por paciente
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

        //graficar medicos
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


        return [
            'chartCitas'=>$chartCitas,
            'chartPacientes' => $chartPacientes,
            'chartMedico' => $chartMedico
        ];

    }

   
}
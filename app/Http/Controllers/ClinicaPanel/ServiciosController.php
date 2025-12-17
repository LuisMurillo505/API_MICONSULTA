<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Servicio;
use App\Services\PlanService;
use App\Services\CitaService;
use App\Services\UsuarioService;
use App\Models\Funciones_planes;
use App\Models\ProgresoUsuarioGuia;
use Carbon\Carbon;
use App\Models\Citas;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ServiciosController extends Controller
{
    protected $planService;
    protected $usuarioService;
    protected $citaService;
    
    public function __construct(PlanService $planServices, UsuarioService $usuarioServices,
        CitaService $citaServices)
    {
        $this->planService = $planServices;
        $this->usuarioService = $usuarioServices;
        $this->citaService = $citaServices;
    }
/**
 * Crea un nuevo servicio para una clínica, validando los límites del plan contratado.
 *
 * Proceso:
 * - Obtiene los datos del usuario y su clínica.
 * - Valida los campos requeridos del servicio.
 * - Verifica si el plan permite crear más servicios.
 * - Crea el servicio con los datos proporcionados.
 * - Marca el paso de la guía como completado si corresponde.
 * - Retorna respuesta JSON, con manejo especial si la petición es AJAX.
 *
 * @param \Illuminate\Http\Request $request
 *        Request que contiene los datos del servicio y el ID del usuario.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso.
 */
    public function store(Request $request){
        try{
            // Obtener datos del usuario y clínica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Validar datos del servicio
            $validated=$request->validate([
                'descripcionS'=>'required|string',
                'duracion'=>'required|integer',
                'precio'=>'required|integer'
            ]);

            // Validar límite de servicios según el plan
            if(!$this->planService->puedeCrearServicio($datos['clinica_id'])){
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de servicios alcanzados',
                    'error'=> 'LIMITE_SERVICIOS'
                ], 404);
            }
 
            // Crear servicio
            $servicio=Servicio::create([
                'clinica_id'=>$datos['clinica_id'],
                'descripcion'=>$request->input('descripcionS'),
                'duracion'=>$validated['duracion'],
                'precio'=>$validated['precio'],
                'status_id'=>1,
                'created_at'=>now(),
                'updated_at'=>now()
            ]);

            // Marcar paso de guía como completado
            if (Servicio::count() >= 1) {
                ProgresoUsuarioGuia::where('usuario_id', $datos['usuario_id'])
                    ->where('clave_paso', 'Agregar_servicio_modal')
                    ->update(['esta_completado' => true]);
            }

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'data'=>compact('servicio')
            ]);

        }catch(\Exception $e){
            if ($request->ajax()) {
                return response()->json([
                    'error' => $e->getMessage()
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
/**
 * Activa o desactiva un servicio según su estado actual,
 * validando los límites permitidos por el plan contratado.
 *
 * Proceso:
 * - Obtiene el servicio por su ID.
 * - Obtiene el plan asociado a la clínica del servicio.
 * - Determina el número máximo de servicios activos permitidos.
 * - Cuenta los servicios activos actuales de la clínica.
 * - Si el servicio está activo, se desactiva.
 * - Si está inactivo, se valida el límite antes de activarlo.
 *
 * @param int $servicio_id
 *        ID del servicio a actualizar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error en la operación.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso.
 */
    public function update(int $servicio_id){
        try{
            // Obtener servicio
            $servicio=Servicio::find($servicio_id);

            // Obtener plan de la clínica
            $plan_id=$servicio->clinica->suscripcion->plan_id;

            // Obtener límite de servicios permitidos por plan
            $serviciosPermitidos=Funciones_planes::where('plan_id',$plan_id)->
                    where('funcion_id',1)->first();

            $permitidos=$serviciosPermitidos->cantidad;

            // Contar servicios activos de la clínica
            $conteoServicios=Servicio::where('clinica_id',$servicio->clinica->id)
            ->where('status_id',1)->count();

            // Si está activo → desactivar
            if ($servicio->status_id == 1) {
                $servicio->update(['status_id' => 2]);
                return response()->json([
                    'success' => true,
                    'data'=>compact('servicio')
                ]);
            }

            // Si está inactivo → validar límite
            if ($permitidos <= $conteoServicios) {
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de servicios alcanzados',
                    'error'=> 'LIMITE_SERVICIOS'
                ], 404);
            }

            $servicio->update(['status_id' => 1]);

              // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'data'=>compact('servicio')
            ]);
             
        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

/**
 * Elimina un servicio por su ID.
 *
 * Este método busca un servicio existente y, si se encuentra,
 * procede a eliminarlo de la base de datos.
 *
 * @param int $servicio_id  ID del servicio a eliminar.
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Exception
 */
    public function delete(int $servicio_id){
        try{
            $servicio=Servicio::find($servicio_id);

            // Verificar si el servicio existe
            if (!$servicio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Servicio no encontrado',
                    'error'   => 'SERVICIO_NO_ENCONTRADO'
                ], 404);
            }

            // Eliminar servicio
            $servicio->delete();

            return response()->json([
                'success' => true,
            ]);
           
        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

/**
 * Genera un reporte estadístico de servicios realizados en un rango de fechas.
 *
 * El reporte permite filtrar por:
 * - Mes (YYYY-MM)
 * - Semana (YYYY-Wxx)
 * - Rango de fechas personalizado
 * - Día actual (por defecto)
 *
 * Incluye:
 * - Total de servicios distintos utilizados
 * - Número de citas por servicio
 * - Ingresos por servicio
 * - Duración total acumulada
 * - Servicio más utilizado
 * - Porcentaje de uso por servicio
 *
 * @param array $datos   Información del usuario/clínica (incluye clinica_id).
 * @param array $request Parámetros de filtro de fechas.
 *
 * @return array|\Illuminate\Http\JsonResponse
 *
 * @throws \Exception
 */
    public function reporteServicios($datos,$request){
        try{
            // Determinar rango de fechas
            $fechaInicio = null;
            $fechaFin = null;

            // Filtro por mes (YYYY-MM)
            if (!empty($request['fecha_mes'])) {
                [$anio, $mes] = explode('-', $request['fecha_mes']);
                $fechaInicio = Carbon::createFromDate($anio, $mes, 1)->startOfMonth();
                $fechaFin = Carbon::createFromDate($anio, $mes, 1)->endOfMonth();
            }

            // Filtro por semana (YYYY-Wxx)
            elseif (!empty($request['fecha_semana'])) {
                // La fecha_semana viene como "2025-W33"
                $fechaInicio = Carbon::parse($request['fecha_semana'])->startOfWeek();
                $fechaFin = Carbon::parse($request['fecha_semana'])->endOfWeek();
            }

            // Rango de fechas personalizado
            elseif (!empty($request['fecha_inicio']) && !empty($request['fecha_fin'])) {
                $fechaInicio = Carbon::parse($request['fecha_inicio']);
                $fechaFin = Carbon::parse($request['fecha_fin']);
            }

            // Por defecto: día actual
            else {
                $fechaInicio = now()->startOfDay();
                $fechaFin = now()->endOfDay();
            }

            // Total de servicios distintos usados
            $CitasServicioTotal = Citas::whereHas('servicio', function ($q) use ($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })
            ->where('status_id', 3)
            ->whereBetween('fecha_cita', [$fechaInicio, $fechaFin])
            ->select( DB::raw('count(distinct servicio_id) as servicios_totales'))
            ->get();

            // Citas por servicio
            $totalDuracion = 0;

            $CitasServicio = Citas::whereHas('servicio', function ($q) use ($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })
            ->where('status_id', 3)
            ->whereBetween('fecha_cita', [$fechaInicio, $fechaFin])
            ->select('servicio_id', DB::raw('count(id) as citas_servicio'))
            ->groupBy('servicio_id')
            ->orderBy('citas_servicio','desc')
            ->with('servicio')
            ->get()
            ->map(function ($item) use(&$totalDuracion) {
                $precio = optional($item->servicio)->precio ?? 0;
                $duracion=optional($item->servicio)->duracion ?? 0;

                $item->ingresos = $item->citas_servicio * $precio;
                $item->duracion_total=$item->citas_servicio*$duracion;

                $totalDuracion +=$item->duracion_total;

                return $item;
            });

            // Servicio más utilizado
            $servicioMasUtilizado=$CitasServicio->sortByDesc('citas_servicio')->first();

            // Duración total en formato HH:mm
            $horas = floor($totalDuracion / 60);
            $minutos = $totalDuracion % 60;
            $duracion = sprintf('%02d:%02d', $horas, $minutos);

            // Citas finalizadas
            $CitasFinalizadas=$this->citaService->reporteCitas($datos,$request);
            // Calcular porcentaje por servicio
            $CitasServicio->transform(function ($item) use ($CitasFinalizadas) {
                $item->porcentaje = $CitasFinalizadas['citas_finalizadas'] > 0
                    ? round(($item->citas_servicio / $CitasFinalizadas['citas_finalizadas']) * 100, 2)
                    : 0;
                return $item;
            });

            // Ingresos totales
            $CitasServiciosIngresos = Citas::whereHas('servicio', function ($q) use ($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })
            ->where('status_id', 3)
            ->whereBetween('fecha_cita', [$fechaInicio, $fechaFin])
            ->with('servicio')
            ->get()
            ->sum(function($item){
                return $item->servicio->precio;
            });

            $CitasServicioTotal = Citas::whereHas('servicio', function ($q) use ($datos) {
                $q->where('clinica_id', $datos['clinica_id']);
            })
            ->where('status_id', 3)
            ->whereBetween('fecha_cita', [$fechaInicio, $fechaFin])
            ->select( DB::raw('count(distinct servicio_id) as servicios_totales'))
            ->get();


            // Respuesta final
             return [
                'nombre_reporte' => 'Reporte de servicio',
                'citas_servicios'=>$CitasServicio ?? null,
                'ServicioMasUtilizado'=>$servicioMasUtilizado ?? null,
                'servicios_total'=>$CitasServicioTotal ?? null,
                'duracion_servicios' => $duracion ?? 0,
                'ingresos_total'=>$CitasServiciosIngresos ?? 0,
                'fechaInicio' => $fechaInicio,
                'fechaFin' => $fechaFin
            ];

        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

/**
 * Genera la gráfica de servicios en formato imagen base64.
 *
 * Construye una gráfica tipo doughnut utilizando los datos del reporte
 * de servicios (citas por servicio) y la API de QuickChart.
 *
 * La gráfica incluye:
 * - Etiquetas con la descripción del servicio
 * - Cantidad de citas por servicio
 * - Colores dinámicos para cada segmento
 *
 * @param array $reporte Información del reporte de servicios.
 *                       Debe contener la clave 'citas_servicios'.
 *
 * @return array Retorna un arreglo con la imagen base64 de la gráfica.
 *
 * @throws \Exception
 */
    public function graficasServicios($reporte){

        // Paleta de colores disponibles para la gráfica
        $coloresDisponibles = [
            "#198754", "#0d6efd", "#ffc107", "#dc3545", "#6f42c1",
            "#20c997", "#fd7e14", "#6610f2", "#0dcaf0", "#adb5bd"
        ];

        // Inicialización de datos para la gráfica
        $labels = [];
        $data = [];
        $colores = [];

        // Construcción de etiquetas, valores y colores
        foreach ($reporte['citas_servicios'] as $index => $item) {
            $labels[] = $item->servicio->descripcion; // o el campo correspondiente (nombre_completo, etc.)
            $data[] = $item->citas_servicio;
            $colores[] = $coloresDisponibles[$index % count($coloresDisponibles)];
        }
         // Configuración de la gráfica para QuickChart
        $chartServiciosConfig = [
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
                        "text" => "Servicios Totales"
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
        // Generar URL de la gráfica
        $chartServiciosUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartServiciosConfig));
        // Obtener imagen y convertir a base64
        $chartServiciosImage = base64_encode(file_get_contents($chartServiciosUrl));
        $chartServicios = "data:image/png;base64," . $chartServiciosImage;

        // Retornar resultado
        return[
            'chartServicios' => $chartServicios
        ];

    }

/**
 * Genera y descarga el reporte de servicios de una clínica.
 *
 * Este método:
 * - Obtiene los datos del usuario y su clínica.
 * - Genera el reporte de servicios según los filtros enviados.
 * - Genera las gráficas correspondientes al reporte.
 * - Retorna toda la información consolidada en formato JSON.
 *
 * @param \Illuminate\Http\Request $request
 *        Request que contiene el usuario y filtros de fechas.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON con la información del reporte y las gráficas.
 *
 * @throws \Exception
 */
    public function descargarReporteServicios(Request $request){
        try{
            // Obtener datos del usuario y clínica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Generar reporte de servicios
            $reporte=$this->reporteServicios($datos,$request->all());
            // Generar gráficas del reporte
            $graficas=$this->graficasServicios($reporte);     
            

            return response()->json([
                'success'=>true,
                'data'=>array_merge(
                    $reporte,
                    $graficas,
                )
            ]);

        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }



}
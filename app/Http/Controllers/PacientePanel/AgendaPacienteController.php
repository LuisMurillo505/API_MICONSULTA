<?php

namespace App\Http\Controllers\PacientePanel;

use App\Models\Clinicas;
use App\Models\Personal;
use App\Models\Servicio;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\Citas;
use App\Models\Status;
use App\Services\UsuarioService;

class AgendaPacienteController extends Controller
{
    protected $usuarioService;    
    public function __construct(UsuarioService $usuarioServices)
    {
        $this->usuarioService = $usuarioServices;
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(string $bookingSlugClinica,?string $bookingSlugMedico=null)
    {
        try{
            
            $clinica=Clinicas::where('booking_slug', $bookingSlugClinica)->first();
            $disponibilidad=null;
            if($bookingSlugMedico){
                $personal=Personal::where('booking_slug',$bookingSlugMedico)->first();
                $disponibilidad=$personal->disponibilidad;
                $citas=Citas::with(['personal.usuario','paciente','servicio'])
                ->whereHas('personal',function($query) use($bookingSlugMedico){
                    $query->where('booking_slug','=',$bookingSlugMedico);
                    // ->where('clinica_id',$clinica->getAttribute('id'));
                })->where('status_id',Status::ACTIVE)
                ->orderBy('fecha_cita', 'asc')
                ->orderBy('hora_inicio', 'asc')
                ->get()
                // Transformar cada cita en un arreglo formateado
                ->map(function ($cita){
                    return[
                        'id' => "Oculto",
                        'fecha_citaFormato'=>Carbon::parse($cita->fecha_cita ?? null)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                        'fecha_cita' => $cita->fecha_cita ?? null,  
                        'hora_inicio' => $cita->hora_inicio ?? null,
                        'hora_fin' => $cita->hora_fin ?? null,
                        'paciente_id'=>'Oculto',
                        'nombre_paciente' => "Ocupado",
                        'alias'=>"Ocupado",
                        'apellidoP_paciente' => "Ocupado",
                        'apellidoM_paciente' => "Ocupado",
                        'nombre_medico' => $cita->personal->nombre ?? null,
                        'apellidoP_medico' => $cita->personal->apellido_paterno ?? null,
                        'apellidoM_medico' => $cita->personal->apellido_materno ?? null,
                        'servicio' => "Oculto",
                        'status' => $cita->status->descripcion ?? null, 
                        'tipocita' => $cita->tipocita->id ?? null  
                    ];
                });
            }else{
                $citas=Citas::with(['personal.usuario','paciente','servicio','status'])
                ->wherehas('personal.usuario',function($q) use($clinica){
                    $q->where('clinica_id',$clinica->getAttribute('id'));
                })->where('status_id',Status::ACTIVE)
                ->orderBy('fecha_cita', 'asc')
                ->orderBy('hora_inicio', 'asc')
                ->get()
                ->map(function ($cita){
                    return[
                        'id' => "Privado",
                        'fecha_citaFormato'=>Carbon::parse($cita->fecha_cita ?? null)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
                        'fecha_cita' => $cita->fecha_cita ?? null,  
                        'hora_inicio' => $cita->hora_inicio ?? null,
                        'hora_fin' => $cita->hora_fin ?? null,
                        'paciente_id'=>"Privado",
                        // 'nombre_paciente' => $cita->paciente->nombre ?? null,
                        'nombre_paciente' => "Ocupado",

                        'alias'=>"Ocupado",
                        'apellidoP_paciente' => "Ocupado",
                        'apellidoM_paciente' =>"Ocupado",
                        'nombre_medico' => "Privado",
                        'apellidoP_medico' => "Privado",
                        'apellidoM_medico' => "Privado",
                        'servicio' => "Privado",
                        'status' => $cita->status->descripcion ?? null, 
                        'tipocita' => $cita->tipocita->id ?? null  
                    ];
                });

            }
           
            //Retorna la respuesta en formato JSON con los datos recopilados.
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'citas',
                    'disponibilidad',
                    'clinica'
                )
            ]);
            
        }catch(Exception $e){
            // Es vital retornar el error para que React sepa qué pasó
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener datos: ' . $e->getMessage()
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
    public function index_createcita(string $bookingSlugClinica,?string $bookingSlugMedico=null){

        try{        
            
            // Obtener el personal que cumple con:
            //    a) Está asignado a un usuario con clinica_id y status_id = 1.
            //    b) Tiene un puesto_id = 2 (medico) o administrador.
            $personalMedico= null;
            if($bookingSlugMedico){
                $personal=Personal::with('usuario')->where('booking_slug',$bookingSlugMedico)->get();
                $personalMedico=Personal::with('usuario')->where('booking_slug',$bookingSlugMedico)->first();
            }else{
                $personal=Personal::whereHas('usuario.clinicas',function($q) use($bookingSlugClinica){
                    $q->where('booking_slug',$bookingSlugClinica)
                        ->where('status_id',1);
                })->where('puesto_id','!=',1)->get();
            }
           
            //Obtener todos los servicios ofrecidos por la clínica específica.
            $servicios=Servicio::whereHas('clinica', function ($q) use($bookingSlugClinica) {
                $q->where('booking_slug',$bookingSlugClinica);
            })->where('status_id',1)->get();

            //Retorna la respuesta en formato JSON con los datos recopilados.
            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'personal',
                            'personalMedico',
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


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
    
    }

    /**
     * Display the specified resource.
     */
    public function show()
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit()
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $almacen_id)
    {
       
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        //
    }
}

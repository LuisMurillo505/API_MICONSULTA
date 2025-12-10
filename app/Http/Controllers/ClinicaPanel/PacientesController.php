<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistorialClinicoRequest;
use App\Http\Requests\PacienteRequest;
use Illuminate\Http\Request;
use App\Models\Observaciones;
use App\Models\ProgresoUsuarioGuia;
use App\Models\ArchivosPaciente;
use App\Services\PacienteService;
use App\Services\PlanService;
use App\Services\UsuarioService;
use App\Models\Pacientes;
use App\Models\Expedientes;
use App\Services\GoogleCloudStorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;



class PacientesController extends Controller
{
     protected $pacienteService;
     protected $planService;
     protected $usuarioService;
     protected $gcs;

    public function __construct(private PacienteService $pacienteServices, 
        PlanService $planServices,UsuarioService $usuarioServices,GoogleCloudStorageService $googleCloudStorageServices) {
        $this->pacienteService = $pacienteServices;
        $this->planService = $planServices;
        $this->usuarioService = $usuarioServices;
        $this->gcs = $googleCloudStorageServices;
    }

    public function store(Request $request){

        try{

            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            if(!$this->planService->puedeCrearPaciente($datos['clinica_id'])){
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de pacientes alcanzados',
                    'error'=> 'LIMITE_PACIENTES'
                ], 404);         
            }
           
            // Calcular edad
            $edad = $this->pacienteService->calcularEdad($request->fecha_nacimiento ?? null);

            // Crear paciente
            $paciente = $this->pacienteService->crearPaciente([
                'clinica_id' => $datos['clinica_id'],
                'nombre' => $request->nombre ?? null,
                'apellido_paterno' => $request->apellido_paterno ?? null,
                'apellido_materno' => $request->apellido_materno ?? null,
                'alias' => $request->alias ?? null,
                'fecha_nacimiento' => $request->fecha_nacimiento ?? null,
                'sexo' => $request->sexo ?? null,
                'edad' => $edad,
                'curp'=>$request->curp ?? null,
                'nss'=>$request->nss ?? null,
                'status_id' => 1,
                'foto' => $request->foto,
                'telefono' => $request->telefono_paciente ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
 
            // Marcar el paso completado si se inserta por primera vez
            if (Pacientes::count() === 1){
                ProgresoUsuarioGuia::where('usuario_id', $datos['usuario_id'])
                    ->where('clave_paso', 'Agregar_paciente_modal')
                    ->update(['esta_completado' => true]);
            }

            // Crear dirección del paciente
            if($request->input('direccion')){
                $direccion = $this->pacienteService->crearDireccion($request->input('direccion') ?? null,$paciente->id);
            }

            // Crear somatometría
            if($request->filled(['peso', 'estatura', 'imc', 'perimetro_cintura', 
                'perimetro_cadera', 'perimetro_brazo', 'perimetro_cefalico'])){
                $this->pacienteService->crearSomatometria($request->only([
                    'peso', 'estatura', 'imc', 'perimetro_cintura',
                    'perimetro_cadera', 'perimetro_brazo', 'perimetro_cefalico'
                ]), $paciente->id ?? null);
            }

            // Crear familiares
            $this->pacienteService->crearFamiliares($request->all(), $paciente->id ?? null);

            // Crear observaciones
            $this->pacienteService->crearObservaciones($request->observaciones ?? null, $paciente->id ?? null);

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'message' => 'Usuario Creado con Exito.',
                'data' => [
                    'paciente' => $paciente,
                ],
            ]);

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);  
        }
    }

     /**
     * Actualiza datos de un paciente existente, su familiar y observaciones.
     */
    
    // public function update(PacienteRequest $request, $paciente_id)
    // {
    //     try {

    //         $datos=$this->usuarioService->DatosUsuario();

    //         // Obtener el paciente
    //         $paciente = Pacientes::find($paciente_id);

    //          // Verificar si el paciente existe
    //         if (!$paciente) {
    //             return back()->with('error', 'Paciente no encontrado');
    //         } 
            
    //         // Manejar la foto
    //         $foto = $paciente->foto ?? null;

    //         if ($request->hasFile('photo')) {
    //             // Subir la nueva foto
    //             if ($request->hasFile('photo')) {
    //                 $foto = $this->pacienteService->guardarFoto($request->file('photo'), $datos['nombre_clinica'],$foto);
    //             }  
    //         }

    //          // Actualizar datos del paciente
    //         $paciente->update([
    //             'nombre'=>$request->nombre,
    //             'apellido_paterno'=>$request->apellido_paterno,
    //             'apellido_materno'=>$request->apellido_materno,
    //             'fecha_nacimiento'=>$request->fecha_nacimiento,
    //             'status_id'=> $request->estado_id ?? null,
    //             'alias'=>$request->alias ?? null,
    //             'telefono'=>$request->telefono_paciente ?? null,
    //             'curp'=>$request->curp ?? null,
    //             'nss'=>$request->nss ?? null,
    //             'foto' => $foto,
    //             'updated_at' => now(),
    //         ]);

    //         // Crear dirección del paciente
    //         if($request->input('direccion')){
    //             $this->pacienteService->crearDireccion($request->input('direccion') ?? null,$paciente_id);
    //         }

    //          // Crear observaciones si se proporcionaron
    //         $this->pacienteService->crearObservaciones($request->observaciones ?? null, $paciente->id ?? null);

    //         //crear o actualizar somatometria
    //         $this->pacienteService->crearSomatometria($request->all(), $paciente->id ?? null);

    //         // Crear familiares
    //         $this->pacienteService->crearFamiliares($request->all(), $paciente->id ?? null);

    //         return back()->with('success', 'Paciente actualizado con éxito');

    //     } catch (Exception $e) {
    //         return back()->with('error', 'Error: ' . $e->getMessage());
    //     }
    // }

    // //Agregar historial clinico del paciente
    // public function historialClinico(HistorialClinicoRequest $request,$paciente_id){
    //     try{
    //         $this->pacienteService->historiaClinica($request->all(),$paciente_id);

    //         return back()->with('success', 'Historial clinico del paciente actualizado con éxito');
    //     }catch(Exception $e){
    //         return back()->with('error', 'Error: ' . $e->getMessage());
    //     }
    // }

    // /**
    //  * Elimina un paciente por su ID.
    //  */
    // public function delete($paciente_id){
    //     try{
    //         $paciente=Pacientes::Find($paciente_id);
    //         $paciente->delete();

    //         return redirect()->route('pacientes.index')->with('success','Paciente Eliminado con exito');

    //     }catch(Exception $e){
    //         return back()->with('error', 'Error: ' . $e->getMessage());
    //     }
    // }   

    //  /**
    //  * Elimina una observación específica por su ID.
    //  */
    // public function deleteNote($observacion_id){
    //     try{
    //         $observaciones=Observaciones::find($observacion_id);
            
    //         $observaciones->delete();

    //         return back()->with('success', 'observacion eliminada correctamente');

    //     }catch(Exception $e){
    //         return back()->with('error', 'Error: ' . $e->getMessage());
    //     }
    // }

    //  /**
    //  * Exporta el expediente de un paciente en formato PDF.
    //  */
    // public function DescargarExpediente($paciente_id,$cita_id)
    // {
    //     $datos=$this->usuarioService->DatosUsuario();

    //     $paciente=Pacientes::find($paciente_id);

    //     if($cita_id!=0){
    //         $expediente=Expedientes::with(['paciente','cita.servicio'])
    //         ->whereHas('paciente', function($q) use($paciente_id){
    //             $q->where('id',$paciente_id);
    //         })->whereHas('cita',function($q) use($cita_id){
    //             $q->where('id',$cita_id);
    //         })->orderby('id','desc')->get();
    //     }else{
    //         $expediente=Expedientes::with(['paciente','cita.servicio'])
    //         ->whereHas('paciente', function($q) use($paciente_id){
    //             $q->where('id',$paciente_id);
    //         })->orderby('id','desc')->get();
    //     }
       

    //     $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();

    //     $pdf = pdf::loadView('admin.descargarExpediente', compact('paciente','expediente','observaciones'),$datos);

    //     return $pdf->download("Expediente_{$paciente->nombre}.pdf");
    // }

    // public function DescargarExpedienteCita($paciente_id,$cita_id)
    // {
    //     $datos=$this->usuarioService->DatosUsuario();

    //     $paciente=Pacientes::find($paciente_id);

    //     $expediente=Expedientes::with(['paciente','cita.servicio'])
    //         ->whereHas('paciente', function($q) use($paciente_id){
    //             $q->where('id',$paciente_id);
    //         })->orderby('id','desc')->get();

    //     $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();

    //     $pdf = pdf::loadView('admin.descargarExpediente', compact('paciente','expediente','observaciones'),$datos);

    //     return $pdf->download("Expediente_{$paciente->nombre}.pdf");
    // }


    // //Subir archivo Paciente
    // public function ArchivosPacientes(Request $request)
    // {
    //     try{

    //         $datos=$this->usuarioService->DatosUsuario();
    //         $paciente=Pacientes::find($request->paciente_id);

    //         $infoArchivos = $this->planService->puedeSubirArchivosPacientes($datos['clinica_id'],$request->paciente_id);

    //         if(!$infoArchivos['puede_subir']) {
    //             return back()->withInput()->with('error',
    //                 "Límite de archivos alcanzado.");
    //         }

    //         // if(!$this->planService->puedeSubirArchivosPacientes($datos['clinica_id'],$request->paciente_id)){
    //         //     return back()->withInput()->with('error', 'Límite de archivos alcanzados');
    //         // }

    //         $this->pacienteService->ArchivosPacientes($request->file('archivo'),$paciente,$datos['clinica']);

    //         return back()->with('success', 'archivo subido correctamente');
    //     }catch(Exception $e){
    //         return back()->with('error', 'Error: ' . $e->getMessage());
    //     }
       
    // }

    // //descargar archivo paciente
    // public function descargar($id)
    // {
    //     $archivo = ArchivosPaciente::findOrFail($id);
    //     $url = $this->gcs->getSignedUrl($archivo->ruta, 5); // URL válida 5 min
    //     return redirect($url);
    // }

    // //Eliminar archivoPaciente
    //  public function destroy($id)
    // {
    //     $archivo = ArchivosPaciente::findOrFail($id);

    //     $this->gcs->delete($archivo->ruta);

    //     $archivo->delete();

    //     return back()->with('success', 'Archivo eliminado correctamente');
    // }
    


    
}

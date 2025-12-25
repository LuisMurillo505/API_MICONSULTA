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

/**
 * Registra un nuevo paciente dentro de una clínica.
 *
 * Proceso:
 * - Obtiene los datos del usuario y su clínica.
 * - Verifica si el plan contratado permite crear más pacientes.
 * - Calcula la edad del paciente a partir de su fecha de nacimiento.
 * - Crea el registro principal del paciente.
 * - Marca el paso de la guía como completado si es el primer paciente.
 * - Crea información adicional opcional:
 *      - Dirección
 *      - Somatometría
 *      - Familiares
 *      - Observaciones
 *
 * @param \App\Http\Requests\PacienteRequest $request
 *        Request validado con los datos del paciente.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error en el proceso.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante la creación del paciente.
 */

    public function store(PacienteRequest $request){

        try{

            // Obtener datos del usuario y clínica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);

            // Validar límite de pacientes según el plan
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
                'foto' => $request->foto ?? null,
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
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);  
        }
    }

/**
 * Actualiza la información de un paciente existente.
 *
 * Proceso:
 * - Obtiene el paciente a partir del ID recibido en el request.
 * - Verifica que el paciente exista.
 * - Actualiza los datos generales del paciente.
 * - Crea o actualiza información relacionada:
 *      - Dirección
 *      - Observaciones
 *      - Somatometría
 *      - Familiares
 *
 * @param \App\Http\Requests\PacienteRequest $request
 *        Request validado que contiene los datos actualizados del paciente.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error en la operación.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso de actualización.
 */
    public function update(PacienteRequest $request)
    {
        try {
            // Obtener el paciente
            $paciente = Pacientes::find($request->paciente_id);

            // Verificar si el paciente existe
            if (!$paciente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Paciente no encontrado',
                    'error'=> 'PACIENTE_NOENCONTRADO'
                ], 404); 
            } 
            
            // Manejar la foto
            // $foto = $paciente->foto ?? null;

            // if ($request->hasFile('photo')) {
            //     // Subir la nueva foto
            //     if ($request->hasFile('photo')) {
            //         $foto = $this->pacienteService->guardarFoto($request->file('photo'), $datos['nombre_clinica'],$foto);
            //     }  
            // }

            // Actualizar datos del paciente
            $paciente->update([
                'nombre'=>$request->nombre,
                'apellido_paterno'=>$request->apellido_paterno,
                'apellido_materno'=>$request->apellido_materno,
                'fecha_nacimiento'=>$request->fecha_nacimiento,
                'status_id'=> $request->estado_id ?? null,
                'alias'=>$request->alias ?? null,
                'telefono'=>$request->telefono_paciente ?? null,
                'curp'=>$request->curp ?? null,
                'nss'=>$request->nss ?? null,
                'foto' => $request->foto ?? null,
                'updated_at' => now(),
            ]);

            // Crear dirección del paciente
            if($request->input('direccion')){
                $this->pacienteService->crearDireccion($request->input('direccion') ?? null,$paciente->id);
            }

             // Crear observaciones si se proporcionaron
            $this->pacienteService->crearObservaciones($request->observaciones ?? null, $paciente->id ?? null);

            //crear o actualizar somatometria
            $this->pacienteService->crearSomatometria($request->all(), $paciente->id ?? null);

            // Crear familiares
            $this->pacienteService->crearFamiliares($request->all(), $paciente->id ?? null);

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'message' => 'Paciente creado correctamente.',
                'data' => [
                    'paciente' => $paciente,
                ],
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
 * Crea o actualiza el historial clínico de un paciente.
 *
 * Proceso:
 * - Recibe un request validado con la información del historial clínico.
 * - Delegada la lógica de creación o actualización al PacienteService.
 * - Retorna una respuesta JSON indicando el resultado de la operación.
 *
 * @param \App\Http\Requests\HistorialClinicoRequest $request
 *        Request validado que contiene los datos del historial clínico
 *        y el ID del paciente.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso.
 */
    public function historialClinico(HistorialClinicoRequest $request){
        try{
            // Crear o actualizar historial clínico
            $this->pacienteService->historiaClinica($request->all(),$request->paciente_id);

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'message' => 'Historial clinico actualizado correctamente.',
            ]);

        }catch(Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

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

/**
 * Elimina una observación clínica por su ID.
 *
 * Proceso:
 * - Busca la observación por su identificador.
 * - Elimina el registro de la base de datos.
 * - Retorna una respuesta JSON indicando el resultado de la operación.
 *
 * @param int $observacion_id
 *        ID de la observación que se desea eliminar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso.
 */
    public function deleteNote(int $observacion_id){
        try{
            // Buscar observación
            $observaciones=Observaciones::find($observacion_id);
            
            // Eliminar observación
            $observaciones->delete();

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'message' => 'Observacion eliminada correctamente',
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
 * Obtiene el expediente clínico de un paciente.
 *
 * Proceso:
 * - Obtiene los datos del paciente.
 * - Consulta los expedientes asociados al paciente.
 * - Si se proporciona un ID de cita distinto de 0, filtra el expediente por dicha cita.
 * - Carga relaciones necesarias: cita, personal, servicio y paciente.
 * - Obtiene las observaciones clínicas del paciente.
 * - Retorna la información en formato JSON.
 *
 * @param int $paciente_id
 *        ID del paciente del cual se desea obtener el expediente.
 *
 * @param int $cita_id
 *        ID de la cita a filtrar.
 *        Si es 0, se obtienen todos los expedientes del paciente.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON con la información del expediente, paciente y observaciones.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso.
 */
    public function DescargarExpediente(int $paciente_id,int $cita_id)
    {
        try{

            // Obtener paciente
            $paciente=Pacientes::find($paciente_id);

            // Obtener expediente clínico
            if($cita_id!=0){
                $expediente=Expedientes::with(['cita.personal','paciente','cita','cita.servicio'])
                ->whereHas('paciente', function($q) use($paciente_id){
                    $q->where('id',$paciente_id);
                })->whereHas('cita',function($q) use($cita_id){
                    $q->where('id',$cita_id);
                })->orderby('id','desc')->get();
            }else{
                $expediente=Expedientes::with(['cita.personal','paciente','cita','cita.servicio'])
                ->whereHas('paciente', function($q) use($paciente_id){
                    $q->where('id',$paciente_id);
                })->orderby('id','desc')->get();
            }
        
            // Obtener observaciones del paciente
            $observaciones=Observaciones::where('paciente_id',$paciente_id)->get();

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
                'data'=>compact('paciente',
                    'expediente','observaciones'),
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
 * Sube y registra archivos asociados a un paciente, validando las
 * restricciones del plan contratado por la clínica.
 *
 * Proceso:
 * - Obtiene los datos del usuario y la clínica.
 * - Obtiene el paciente al que se le asociarán los archivos.
 * - Verifica si el plan permite subir archivos para el paciente.
 * - Si no está permitido, retorna un error.
 * - Si está permitido, delega la subida y registro de archivos al PacienteService.
 *
 * @param \Illuminate\Http\Request $request
 *        Request que contiene el archivo a subir, el ID del paciente
 *        y el ID del usuario.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso.
 */
    public function ArchivosPacientes(Request $request)
    {
        try{

            // Obtener datos del usuario y clínica
            $datos=$this->usuarioService->DatosUsuario($request->usuario_id);
            // Obtener paciente
            $paciente=Pacientes::find($request->paciente_id);

            // Validar si el plan permite subir archivos
            $infoArchivos = $this->planService->puedeSubirArchivosPacientes($datos['clinica_id'],$request->paciente_id);

            if(!$infoArchivos['puede_subir']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puede subir archivos',
                    'error'=> 'NOPUEDE_SUBIR'
                ], 404);
            }

             // Subir archivo del paciente
            $this->pacienteService->ArchivosPacientes($request->file('archivo'),$paciente,$datos['clinica']);

            // Retornar respuesta JSON
            return response()->json([
                'success' => true,
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
 * Genera una URL temporal firmada para descargar un archivo de un paciente.
 *
 * Proceso:
 * - Busca el archivo del paciente por su ID.
 * - Genera una URL firmada con tiempo de expiración limitado.
 * - Retorna la URL en formato JSON para su descarga segura.
 *
 * Importante:
 * - La URL generada es válida únicamente por 5 minutos.
 *
 * @param int $archivo_id
 *        ID del archivo que se desea descargar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON con la URL temporal del archivo.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso.
 */
    public function descargarArchivo(int $archivo_id)
    {
        try{
            // Obtener archivo del paciente
            $archivo = ArchivosPaciente::findOrFail($archivo_id);
             // Generar URL firmada (válida por 5 minutos)
            $url = $this->gcs->getSignedUrl($archivo->ruta, 5);
            return response()->json([
                'success'=>true,
                'data'=>['url'=>$url]
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
 * Elimina un archivo asociado a un paciente.
 *
 * Proceso:
 * - Busca el archivo del paciente por su ID.
 * - Elimina el archivo físico del almacenamiento (Google Cloud Storage).
 * - Elimina el registro del archivo en la base de datos.
 * - Retorna una respuesta JSON indicando el resultado de la operación.
 *
 * @param int $archivo_id
 *        ID del archivo que se desea eliminar.
 *
 * @return \Illuminate\Http\JsonResponse
 *         Respuesta JSON indicando éxito o error.
 *
 * @throws \Exception
 *         Si ocurre cualquier error durante el proceso.
 */
    public function destroy(int $archivo_id)
    {
        try{
            // Obtener archivo del paciente
            $archivo = ArchivosPaciente::findOrFail($archivo_id);

            // Eliminar archivo del almacenamiento
            $this->gcs->delete($archivo->ruta);

            // Eliminar registro del archivo
            $archivo->delete();

            return response()->json([
                'success'=>true,
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

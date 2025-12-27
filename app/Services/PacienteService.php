<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Observaciones;
use Exception;
use Carbon\Carbon;
use App\Models\Pacientes;
use App\Models\Somatometria_Paciente;
use App\Models\Clinicas;
use App\Models\Direcciones;
use App\Models\Familiar_paciente;
use App\Models\ArchivosPaciente;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PacienteService
{
    protected $gcs;

    public function __construct(GoogleCloudStorageService $gcs)
    {
        $this->gcs = $gcs;
    }

/**
 * Crea o actualiza la dirección asociada a un paciente.
 *
 * Este método recibe un arreglo opcional de datos de dirección y el ID del paciente.
 * Si al menos uno de los campos (calle, ciudad o localidad) contiene información:
 *  - Si el paciente ya tiene una dirección registrada, esta se actualiza.
 *  - Si no tiene dirección, se crea una nueva y se asocia al paciente.
 *
 * Si no se proporciona ningún dato de dirección válido, no se realiza ninguna acción.
 *
 * @param array|null $datos Arreglo con los datos de la dirección. Puede contener:
 *                          - 'calle' (string)
 *                          - 'ciudad' (string)
 *                          - 'localidad' (string)
 * @param int $paciente_id ID del paciente al que se asociará la dirección.
 *
 * @return Direcciones|null Retorna la dirección creada o actualizada.
 *                          Retorna null si no se proporcionan datos de dirección.
 *
 * @throws \Exception Lanza una excepción si ocurre un error durante el proceso.
 */
    public function crearDireccion(?array $datos,$paciente_id): ?Direcciones
    {
      
      try
      {
        $paciente = Pacientes::find($paciente_id);

        $data=['calle' => $datos['calle'] ?? '',
              'ciudad' => $datos['ciudad'] ?? '',
              'localidad' => $datos['localidad'] ?? ''];

        // Verificar si al menos un campo de la dirección fue proporcionado
        if ($datos['calle'] || $datos['ciudad'] || $datos['localidad']) {
          if($paciente !== null && $paciente->direccion)
          {
            // Actualizar dirección existente
            $paciente->direccion()->update($data);

            return $paciente->direccion ?? null;
          }else
          {
            // Crear nueva dirección
            return $paciente->direccion()->create($data);
          }
           
        }
        return null;
      }catch(Exception $e){
        throw $e;
      }  
    }
/**
 * Guarda la fotografía de un paciente en el almacenamiento público.
 *
 * - Elimina la fotografía anterior si existe.
 * - Genera un nombre único basado en el timestamp.
 * - Mueve el archivo a la ruta correspondiente dentro de storage.
 *
 * @param \Illuminate\Http\UploadedFile $file  Archivo de imagen a guardar.
 * @param string $ruta                       Carpeta base donde se almacenará la imagen.
 * @param string|null $oldphoto              Nombre de la fotografía anterior (si existe).
 *
 * @return string                            Nombre del archivo guardado.
 *
 * @throws \Exception                       Si ocurre un error durante el proceso.
 */
    public function guardarFoto($file, $ruta,$oldphoto): string
    {
      try{
        // Eliminar foto antigua si existe
        if ($oldphoto && Storage::disk('public')->exists($ruta.'/pacientes/' . $oldphoto)) {
          Storage::disk('public')->delete($ruta.'/pacientes/' . $oldphoto);
        }
        $nombre = time().'.'.$file->getClientOriginalExtension();
        $file->move(storage_path("app/public/$ruta/pacientes"), $nombre);
        return $nombre;
      }catch(Exception $e){
        throw $e;
      }
        
    }

  /**
 * Calcula la edad actual a partir de una fecha de nacimiento.
 *
 * Este método utiliza Carbon para determinar la edad en años completos
 * tomando como referencia la fecha actual del sistema.
 *
 * @param string $fecha_nac Fecha de nacimiento en formato válido para Carbon (Y-m-d recomendado).
 *
 * @return int Edad calculada en años.
 *
 * @throws \Exception Si la fecha no es válida o ocurre un error al parsearla.
 */
    public function calcularEdad(string $fecha_nac): int
    {
      try{
        return Carbon::parse($fecha_nac)->age;

      }catch(Exception $e){
        throw $e;
      }
    }

/**
 * Crea un nuevo paciente en la base de datos.
 *
 * Este método recibe un arreglo con los datos del paciente y
 * crea un registro utilizando el modelo Pacientes.
 *
 * @param array $data  Datos del paciente (nombre, apellidos, fecha_nacimiento, etc.).
 *
 * @return Pacientes   Instancia del paciente creado.
 *
 * @throws \Exception  Si ocurre algún error durante la creación del registro.
 */
    public function crearPaciente(array $data): Pacientes
    {
      try{
        return Pacientes::create($data);
      }catch(Exception $e){
        throw $e;
      }
    }

/**
 * Registra observaciones asociadas a un paciente.
 *
 * Recorre un arreglo opcional de observaciones y guarda únicamente aquellas
 * que no estén vacías o compuestas solo por espacios. Cada observación válida
 * se almacena como un registro independiente relacionado con el paciente.
 *
 * @param array|null $observaciones  Arreglo de observaciones en texto plano.
 *                                   Puede ser null o vacío.
 * @param int        $paciente_id    ID del paciente al que pertenecen
 *                                   las observaciones.
 *
 * @return void
 *
 * @throws \Exception Si ocurre algún error durante el guardado en base de datos.
 */
    public function crearObservaciones(?array $observaciones, int $paciente_id): void
    {
      try{
          if ($observaciones && is_array($observaciones)) {
              foreach ($observaciones as $obs) {
                  if ($obs && trim($obs) !== '') {
                      Observaciones::create([
                          'observacion' => $obs,
                          'paciente_id' => $paciente_id,
                      ]);
                  }
              }
          }
      }catch(Exception $e){
          throw $e;
      }  
    }

/**
 * Crea o actualiza la somatometría de un paciente.
 *
 * Este método registra o actualiza los datos antropométricos del paciente
 * (peso, estatura, IMC y perímetros).  
 * Solo se guarda información si al menos uno de los valores es proporcionado.
 *
 * Si el paciente ya cuenta con un registro de somatometría, se actualiza;
 * de lo contrario, se crea uno nuevo.
 *
 * @param array|null $datos Datos de somatometría del paciente.
 *                        
 * @param int $paciente_id ID del paciente.
 *
 * @throws \Exception Si ocurre un error durante el proceso.
 *
 * @return void
 */
    public function crearSomatometria(array $datos, int $paciente_id): void
    {
      try{
        
        $paciente=Pacientes::find($paciente_id);

        $data=[
          'paciente_id' => $paciente_id,
          'peso' => $datos['peso'] ?? null,
          'estatura' => $datos['estatura'] ?? null,
          'IMC' => $datos['imc'] ?? null,
          'perimetro_cintura' => $datos['perimetro_cintura'] ?? null,
          'perimetro_cadera' => $datos['perimetro_cadera'] ?? null,
          'perimetro_brazo' =>$datos['perimetro_brazo'] ?? null,
          'perimetro_cefalico' => $datos['perimetro_cefalico'] ?? null, 
        ];

        if($datos['peso']|| $datos['estatura'] || $datos['imc'] || $datos['perimetro_cintura'] 
              || $datos['perimetro_cadera'] || $datos['perimetro_brazo'] || $datos['perimetro_cefalico']){
            if($paciente->somatometria){
              $paciente->somatometria->update($data);
            }else{
              Somatometria_Paciente::create($data);
            }
           
        }
                   
        }catch(Exception $e){
            throw $e;
        }
       
    }

/**
 * Crea o actualiza los familiares asociados a un paciente.
 *
 * Este método procesa arreglos de datos enviados desde un formulario,
 * permitiendo crear nuevos familiares o actualizar los existentes,
 * junto con su dirección correspondiente.
 *
 * @param array $datos  Arreglo con la información de los familiares
 *                           
 * @param int   $paciente_id  ID del paciente al que pertenecen los familiares
 *
 * @return void
 *
 * @throws \Exception
 */
    public function crearFamiliares(array $datos, int $paciente_id): void
    {
      try{
            $nombreFamiliar = $datos['nombre_familiar'] ?? [];
            $apFamiliar = $datos['ap_familiar'] ?? [];
            $amFamiliar = $datos['am_familiar'] ?? [];
            $parentesco = $datos['parentesco'] ?? [];
            $telefonos = $datos['telefono_familiar'] ?? [];
            $calle = $datos['direccion_fam']['calle'] ?? [];
            $localidad = $datos['direccion_fam']['localidad'] ?? [];
            $ciudad = $datos['direccion_fam']['ciudad'] ?? [];

            // IDs existentes en base de datos (si se envían desde el formulario)
            $familiarIds = $datos['familiar_id'] ?? [];

            for ($i = 0; $i < count($nombreFamiliar); $i++) {
                $tieneDatos = $nombreFamiliar[$i] || $apFamiliar[$i] || $amFamiliar[$i] || $telefonos[$i];

                if ($tieneDatos) {
                    $data = [
                        'paciente_id'       => $paciente_id,
                        'nombre'            => $nombreFamiliar[$i] ?? '',
                        'parentesco'        =>  $parentesco[$i] ?? 'Sin definir',
                        'apellido_paterno'  => $apFamiliar[$i] ?? '',
                        'apellido_materno'  => $amFamiliar[$i] ?? '',
                        'telefono'          => $telefonos[$i] ?? '',
                    ];
                    $data_fam = [
                        'calle'            => $calle[$i] ?? '',
                        'localidad'  => $localidad[$i] ?? '',
                        'ciudad'  => $ciudad[$i] ?? '',
                    ];

                    if (!empty($familiarIds[$i])) {
                        // Actualizar familiar existente
                        $familiar = Familiar_paciente::find($familiarIds[$i]);

                        if ($familiar) {
                            $familiar->update($data);
                            $familiar->direccion()->update($data_fam);
                        }
                    } else {
                        // Crear nuevo familiar
                        $familiar=Familiar_paciente::create([
                            'paciente_id'       => $paciente_id,
                            'nombre'            => $nombreFamiliar[$i] ?? '',
                            'parentesco'        => $parentesco[$i] ?? 'Sin definir',
                            'apellido_paterno'  => $apFamiliar[$i] ?? '',
                            'apellido_materno'  => $amFamiliar[$i] ?? ''  ,
                            'telefono'          => $telefonos[$i] ?? '',
                        ]);
                        $familiar->direccion()->create($data_fam);
                        
                    }
                }
            }
      }catch(Exception $e){
        throw $e;
      }
       
    }

/**
 * Crea o actualiza la historia clínica de un paciente.
 *
 * Este método registra toda la información clínica del paciente, incluyendo:
 * - Datos de identificación hospitalaria
 * - Motivo de consulta y enfermedad actual
 * - Antecedentes patológicos y no patológicos
 * - Antecedentes heredofamiliares
 * - Antecedentes gineco-obstétricos
 * - Signos vitales
 * - Exploración física
 * - Estudios de laboratorio e imagen
 * - Diagnóstico y plan terapéutico
 *
 * Si el paciente ya cuenta con un historial clínico, la información se actualiza.
 * En caso contrario, se crea un nuevo registro asociado al paciente.
 *
 * @param array|null $validated  Datos validados provenientes del formulario de historia clínica.
 *                               Puede contener valores nulos o booleanos según el campo.
 * @param int        $paciente_id ID del paciente al que se asociará la historia clínica.
 *
 * @return void
 *
 * @throws \Exception Si ocurre algún error al buscar el paciente o al guardar la información.
 */
    public function historiaClinica(?array $validated,$paciente_id){
        try{
          $paciente=pacientes::find($paciente_id);

          $data = [
            'registro_num' => $validated['registro_num'] ?? null,
            'ocupacion' => $validated['ocupacion'] ?? null,
            'cuarto' => $validated['cuarto'] ?? null,
            'sala' => $validated['sala'] ?? null,
            'motivo_consulta' => $validated['motivo_consulta'] ?? null,
            'enfermedad_actual' => $validated['enfermedad_actual'] ?? null,

            'cardiovasculares' => $validated['cardiovasculares'] ?? false,
            'pulmonares' => $validated['pulmonares'] ?? false,
            'digestivos' => $validated['digestivos'] ?? false,
            'diabetes' => $validated['diabetes'] ?? false,
            'renales' => $validated['renales'] ?? false,
            'quirurgicos' => $validated['quirurgicos'] ?? false,
            'alergicos' => $validated['alergicos'] ?? false,
            'transfusiones' => $validated['transfusiones'] ?? false,

            'medicamentos' => $validated['medicamentos'] ?? null,
            'med_especificar' => $validated['med_especificar'] ?? null,

            'alcohol' => $validated['alcohol'] ?? false,
            'tabaquismo' => $validated['tabaquismo'] ?? false,
            'drogas' => $validated['drogas'] ?? false,
            'inmunizaciones' => $validated['inmunizaciones'] ?? false,
            'otros_no_patologicos' => $validated['otros_no_patologicos'] ?? null,

            'padre_vivo' => $validated['padre_vivo'] ?? false,
            'padre_enfermedades' => $validated['padre_enfermedades'] ?? null,
            'madre_viva' => $validated['madre_viva'] ?? false,
            'madre_enfermedades' => $validated['madre_enfermedades'] ?? null,
            'hermanos' => $validated['hermanos'] ?? null,
            'hermanos_enfermedades' => $validated['hermanos_enfermedades'] ?? null,
            'fam_otros' => $validated['fam_otros'] ?? null,

            'menarquia' => $validated['menarquia'] ?? null,
            'ritmo' => $validated['ritmo'] ?? null,
            'fum' => $validated['fum'] ?? null,
            'ivsa' => $validated['ivsa'] ?? null,
            'g' => $validated['g'] ?? null,
            'p' => $validated['p'] ?? null,
            'a' => $validated['a'] ?? null,
            'c' => $validated['c'] ?? null,
            'usa_anticonceptivos' => $validated['usa_anticonceptivos'] ?? false,
            'cuales_anticonceptivos' => $validated['cuales_anticonceptivos'] ?? null,

            'ta' => $validated['ta'] ?? null,
            'fc' => $validated['fc'] ?? null,
            'fr' => $validated['fr'] ?? null,
            'temp' => $validated['temp'] ?? null,

            'cabeza' => $validated['cabeza'] ?? null,
            'cuello' => $validated['cuello'] ?? null,
            'torax' => $validated['torax'] ?? null,
            'abdomen' => $validated['abdomen'] ?? null,
            'genitales' => $validated['genitales'] ?? null,
            'extremidades' => $validated['extremidades'] ?? null,
            'neurologico' => $validated['neurologico'] ?? null,

            'laboratorio' => $validated['laboratorio'] ?? null,
            'estudios_imagen' => $validated['estudios_imagen'] ?? null,
            'otros_examenes' => $validated['otros_examenes'] ?? null,

            'diagnostico' => $validated['diagnostico'] ?? null,
            'plan_terapeutico' => $validated['plan_terapeutico'] ?? null,
            'medico_tratante' => $validated['medico_tratante'] ?? null,
          ];

          if($paciente->historial_clinico){
            $paciente->historial_clinico()->update($data);
          }else{
            $paciente->historial_clinico()->create($data);
          }
      }catch(Exception $e){
        throw $e;
      }
    }

    // public function ArchivosPacientes($nombre,$tipo,$tamano,$paciente,$clinica)
    // {
    //   try{
        
    //     // Subir a Google Cloud Storage
    //     $path = "pacientes/{$clinica->nombre}/{$paciente->id} {$paciente->nombre}/" . $nombre;

    //     // // Subir archivo al bucket
    //     // $this->gcs->upload($archivo->getRealPath(), $path);
        
    //     // Guardar en BD
    //     $registro = ArchivosPaciente::create([
    //         'status_id'=>1,
    //         'paciente_id' => $paciente->id,
    //         'nombre' => $nombre,
    //         'ruta' => $path,
    //         'tipo' => $tipo,
    //         'tamano' => $tamano,
    //     ]);

    //   }catch(Exception $e){
    //     throw $e;
    //   }
    // }

/**
 * Sube un archivo asociado a un paciente a Google Cloud Storage
 * y registra su información en la base de datos.
 *
 * El archivo se guarda dentro de una carpeta estructurada por clínica
 * y paciente, y posteriormente se crea el registro en la tabla
 * de archivos del paciente.
 *
 * @param \Illuminate\Http\UploadedFile $archivo  Archivo enviado desde el formulario.
 * @param \App\Models\Pacientes         $paciente Modelo del paciente asociado al archivo.
 * @param array                         $clinica  Datos de la clínica (incluye nombre).
 *
 * @return void
 *
 * @throws \Exception Si ocurre un error durante la carga o el guardado.
 */
     public function ArchivosPacientes($archivo,$paciente,$clinica)
    {
      try{
        
        // Subir a Google Cloud Storage
        $path = "pacientes/{$clinica['nombre']}/{$paciente['id']} {$paciente['nombre']}/" . $archivo->getClientOriginalName();

        // Subir archivo al bucket
        $this->gcs->upload($archivo->getRealPath(), $path);
        
        //Guardar en BD
        $registro = ArchivosPaciente::create([
            'status_id'=>1,
            'paciente_id' => $paciente->id,
            'nombre' => $archivo->getClientOriginalName(),
            'ruta' => $path,
            'tipo' => $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize(),
        ]);

      }catch(Exception $e){

      }
    
    }

}
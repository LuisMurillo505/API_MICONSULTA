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

     public function crearDireccion(?array $datos,$paciente_id): ?Direcciones
    {
      
      try
      {
        $paciente = Pacientes::find($paciente_id);

        $data=['calle' => $datos['calle'] ?? '',
              'ciudad' => $datos['ciudad'] ?? '',
              'localidad' => $datos['localidad'] ?? ''];

        if ($datos['calle'] || $datos['ciudad'] || $datos['localidad']) {
          if($paciente !== null && $paciente->direccion)
          {
            $paciente->direccion()->update($data);

            return $paciente->direccion ?? null;
          }else
          {
            return $paciente->direccion()->create($data);
          }
           
        }
        return null;
      }catch(Exception $e){
        throw $e;
      }

       
    }
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

    public function calcularEdad(string $fecha_nac): int
    {
      try{
        return Carbon::parse($fecha_nac)->age;

      }catch(Exception $e){
        throw $e;
      }
    }

    public function crearPaciente(array $data): Pacientes
    {
      try{
        return Pacientes::create($data);
      }catch(Exception $e){
        throw $e;
      }
    }

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
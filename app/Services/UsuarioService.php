<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\Http;
use Exception;
use Carbon\Carbon;
use App\Models\Personal;
use App\Models\PasoGuia;
use App\Models\ProgresoUsuarioGuia;
use App\Models\Direcciones;
use App\Models\Notificaciones;
use App\Models\Clinicas;
use App\Models\Citas;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\Disponibilidad;

class UsuarioService
{
    public function DatosUsuario($usuario_id){
        try{
            $user = Usuario::find($usuario_id);
    
            $datos=[
                'usuario'=>$user,
                'correo'=>$user->correo,
                'usuario_id'=>$user->id,
                'clinica'=>$user->clinicas,
                'plan_clinica'=>$user->clinicas->suscripcion->plan->nombre,
                'tiempo_plan'=>$user->clinicas->suscripcion->dias_restantes,
                'clinica_id'=>$user->clinicas->id,
                'nombre_clinica'=>$user->clinicas->nombre,
                'puesto_usuario'=>$user->personal?->puesto?->descripcion ?? null,
                'foto_personal'=>$user->personal->foto ?? null,
                'personal_id'=>$user->personal->id ?? null,
                'Nombre_usuario'=>$user->personal->nombre ?? null,
                'notificaciones'=>Notificaciones::where('personal_id','=',$user->personal->id ?? null)->orderBy('id','desc')->get() ?? null,
                'notificaciones_no_leidas'=>Notificaciones::where('estado',1)->where('personal_id',$user->personal->id ?? null)->count() ?? null
            ];

            return $datos;
            
        }catch(\Throwable $e){
            // return response()->json([
            //     'success' => false,
            //     'message' => 'Error al obtener datos',
            //     'error' => $e->getMessage(),
            // ], 500);
            throw $e;
        }
        
    }

    public function obtenerDatosGuia($usuario_id)
    {

       // Total de pasos en la guía (todos los pasos activos no existen, se asume que todos están activos)
        $total_pasos = PasoGuia::count();

        $total_pasosF = 0;
        if ($usuario_id) {
            $total_pasosF = ProgresoUsuarioGuia::where('usuario_id', $usuario_id)
                ->where('esta_completado', true)
                ->count();
        }
        $pasosT = PasoGuia::all()->count();
        $clave_paso = PasoGuia::where('id', 1)->value('clave_paso');
        $paso_completo = [];
        $paso_completo = ProgresoUsuarioGuia::where('usuario_id', $usuario_id)->where('esta_completado', true)->pluck('clave_paso');
        $PasoGuia2 = PasoGuia::with(['progreso' => function ($q) use ($usuario_id) {
            $q->where('usuario_id', $usuario_id);
        }])->get();

        return compact('total_pasos', 'total_pasosF', 'pasosT', 'clave_paso', 'paso_completo', 'PasoGuia2');
    }

    public function registrarUsuarioClinica(?array $data):Usuario{
        try{
            // Crear clínica sin activar plan aún
            $clinica = Clinicas::create([
                'nombre' => $data['clinica'],
                'telefono' =>  $data['telefono'],
            ]);

             // Crear dirección
            $clinica->direccion()->create([
                'calle' => $data['direccion']['calle'],
                'ciudad' => $data['direccion']['ciudad'],
                'localidad' => $data['direccion']['localidad'],
            ]);
             

            // Crear usuario
            $usuario = Usuario::create([
                'clinica_id' => $clinica->id,
                'correo' => $data['correo'],
                'password' => Hash::make($data['password']),
                'status_id' => 5, // Se activará después del pago
            ]);

            $usuario->sendEmailVerificationNotification();

            return $usuario;

        }catch(Exception $e){
            throw $e;
        }
    }
    public function guardarFoto($file, $ruta,$oldphoto): string
    {
      try{
        // Eliminar foto antigua si existe
        if ($oldphoto && Storage::disk('public')->exists($ruta.'/usuarios/' . $oldphoto)) {
          Storage::disk('public')->delete($ruta.'/usuarios/' . $oldphoto);
        }
        $nombre = time().'.'.$file->getClientOriginalExtension();
        $file->move(storage_path("app/public/$ruta/usuarios"), $nombre);
        return $nombre;
      }catch(Exception $e){
        throw $e;
      }
        
    }

    public function guardarFotoClinica($file, $ruta,$oldphoto): string
    {
      try{
        // Eliminar foto antigua si existe
        if ($oldphoto && Storage::disk('public')->exists($ruta.'/clinica/' . $oldphoto)) {
          Storage::disk('public')->delete($ruta.'/clinica/' . $oldphoto);
        }
        $nombre = time().'.'.$file->getClientOriginalExtension();
        $file->move(storage_path("app/public/$ruta/clinica"), $nombre);
        return $nombre;
      }catch(Exception $e){
        throw $e;
      }
        
    }

    public function disponibilidad(?array $dias, int $personal_id):void{
        try{

            Disponibilidad::where('personal_id', $personal_id)->delete();

             foreach($dias as $dia => $datos){
                if(isset($datos['activo']) && !empty($datos['hora_inicio']) && !empty($datos['hora_fin'])){
                    $hora_inicio = Carbon::parse($datos['hora_inicio']);
                    $hora_fin = Carbon::parse($datos['hora_fin']);

                    if ($hora_inicio->gte($hora_fin)) {
                        $personal=Personal::find($personal_id);
                        $personal->usuario->delete();
                        throw new Exception("Error en el día $dia: la hora de inicio no puede ser mayor o igual a la hora de fin.");
                    }
                        Disponibilidad::create([
                        'personal_id' => $personal_id,
                        'dia' => $dia,
                        'hora_inicio' => $datos['hora_inicio'],
                        'hora_fin' => $datos['hora_fin'],
                    ]);
                }
            }
        }catch(Exception $e){
            throw $e;
        }
    }

     public function update_disponibilidad(?array $dias, int $personal_id):void{
        try{

            Disponibilidad::where('personal_id', $personal_id)->delete();

             foreach($dias as $dia => $datos){
                if(isset($datos['activo']) && !empty($datos['hora_inicio']) && !empty($datos['hora_fin'])){
                    $hora_inicio = Carbon::parse($datos['hora_inicio']);
                    $hora_fin = Carbon::parse($datos['hora_fin']);

                    if ($hora_inicio->gte($hora_fin)) {
                        throw new Exception("Error en el día $dia: la hora de inicio no puede ser mayor o igual a la hora de fin.");
                    }
                        Disponibilidad::create([
                        'personal_id' => $personal_id,
                        'dia' => $dia,
                        'hora_inicio' => $datos['hora_inicio'],
                        'hora_fin' => $datos['hora_fin'],
                    ]);
                }
            }
        }catch(Exception $e){
            throw $e;
        }
    }

    //Checa si el personal tiene disponibilidad en un dia especifico y en un rango de horas
    public function disponibilidad_dia(int $personal_id, Carbon $fecha, Carbon $hora_inicio, Carbon $hora_fin){
        try{
            $diaSemana=strtolower($fecha->locale('es')->dayName);
            $disponibilidad=Disponibilidad::where('personal_id',$personal_id)
                    ->where('dia',$diaSemana)->first();

            if (!$disponibilidad) return false;

            $disponibilidad_inicio = Carbon::createFromFormat('H:i:s', $disponibilidad->hora_inicio);
            $disponibilidad_fin = Carbon::createFromFormat('H:i:s', $disponibilidad->hora_fin);

            return $hora_inicio->gte($disponibilidad_inicio) && $hora_fin->lte($disponibilidad_fin);
            
        }catch(Exception $e){
            throw $e;
        }
    }


    //analiza si el personal no tiene otra cita agendada en una fecha y hora especifica
    public function personal_citas(?array $datos,$hora_inicio,$hora_fin):void{
      // Checar disponibilidad del médico
        $medico_citas = citas::where('personal_id', $datos['medico'])
            ->where('status_id',1)
            ->whereDate('fecha_cita', $datos['fecha'])
            ->get();         

        $disponible = true;

        foreach ($medico_citas as $cita) {
            $cita_inicio = Carbon::createFromFormat('H:i:s', $cita->hora_inicio);
            $cita_fin = Carbon::createFromFormat('H:i:s', $cita->hora_fin);

            if (
                $hora_inicio->lt($cita_fin) && 
                $hora_fin->gt($cita_inicio)
            ) {
                $disponible = false;
                break;
            }
        }

        if (!$disponible) {
            throw new Exception("Medico no disponible en ese horario.");
        }
    }
}
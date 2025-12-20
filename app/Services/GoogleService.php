<?php

namespace App\Services;

use App\Models\Clinicas;
use ArrayAccess;
use Illuminate\Support\Facades\Http;
use Exception;
use Carbon\Carbon;
use App\Models\Citas;
use App\Models\Servicio;
use App\Models\Usuario;
use App\Models\Personal;
use App\Models\Pacientes;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Models\Disponibilidad;
use Google_Service_Calendar;
use Google_Service_Calendar_Event;
use Google_Client;

class GoogleService
{
    protected $usuarioService;

    public function __construct(usuarioService $usuarioService)
    {
        $this->usuarioService = $usuarioService;
    }

    public function clienteGoogle($usuario){

        
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessToken([
            'access_token' => $usuario->google->google_token,
            'refresh_token' => $usuario->google->google_refresh_token,
            'expires_in' => $usuario->google->google_token_expires_in->diffInSeconds(now()),
        ]);

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($usuario->google->google_refresh_token);
            $newToken = $client->getAccessToken();

            // Actualizar tokens
            $usuario->google->google_token = $newToken['access_token'];
            $usuario->google->google_token_expires_in = now()->addSeconds($newToken['expires_in']);
            $usuario->google->save();
        }

        return $client;
    }

    public function UsuarioCreador(int $medico_id,int $clinica_id){
        try{
            $medico=Personal::findOrFail($medico_id);
            $admin=Usuario::where('clinica_id',$clinica_id)->first();

            $usuarioCreador = null;
            $attendees = [];

            if ($admin && $admin->google && $admin->google->google_token) {
                $usuarioCreador = $admin;
                if($medico->usuario->google && $medico->usuario->google->google_token){
                    $attendees[] = ['email' => $medico->usuario->correo];
                }
            } elseif ($medico && $medico->usuario && $medico->usuario->google && $medico->usuario->google->google_token) {
                $usuarioCreador = $medico->usuario;
            } 

            $data=[
                'usuarioCreador'=>$usuarioCreador,
                'attendees'=>$attendees
            ];

            return $data;

        }catch(Exception $e){

        }
    }
    

    function crearEventoGoogleCalendar($cita,$usuarioCreador,$fecha, $hora_inicio,$hora_fin)
    {

        $servicio=Servicio::find($cita->servicio_id);
        $paciente=Pacientes::find($cita->paciente_id);
        $medico=Personal::find($cita->personal_id);
        $attendees = $usuarioCreador['attendees'];

        $inicio = Carbon::parse("$fecha $hora_inicio");
        $fin = Carbon::parse("$fecha $hora_fin");

        $client=$this->clienteGoogle($usuarioCreador['usuarioCreador']);

        $service = new Google_Service_Calendar($client);

        $event = new Google_Service_Calendar_Event([
            'summary' => $servicio->descripcion,
            'description'=>"Cita con el paciente: $paciente->nombre $paciente->apellido_paterno, Medico: $medico->nombre $medico->apellido_paterno",
            'start' => ['dateTime' => $inicio->toRfc3339String(),
                'timeZone' => 'America/Mexico_City'],
            'end' => ['dateTime' => $fin->toRfc3339String(),
            'timeZone' => 'America/Mexico_City',],
            'attendees' => $attendees,
            
        ]);

        $calendarId = 'primary'; // o ID específico si usa varios
        $eventCreated=$service->events->insert($calendarId, $event);
        $cita->event_google_id = $eventCreated->id;
        $cita->google_owner_id = $usuarioCreador['usuarioCreador']->id; 
        $cita->save();

        return $eventCreated;

    }

    // public function eliminarEvento($eventoId,$cita)
    // {
    //     $usuario = Usuario::find($cita->google_owner_id);

    //     if (!$usuario || !$usuario->google) {
    //             throw new Exception("No se puede eliminar el evento: el usuario no tiene token válido");
    //     }

    //     $client=$this->clienteGoogle($usuario);

    //     $service = new Google_Service_Calendar($client);
    //     return $service->events->delete('primary', $eventoId);
    // }

     public function eliminarEvento($eventoId,array $usuarioCreador)
    {
        $usuario = Usuario::find($usuarioCreador['usuarioCreador']['id']);

        if (!$usuario || !$usuario->google) {
                throw new Exception("No se puede eliminar el evento: el usuario no tiene token válido");
        }

        $client=$this->clienteGoogle($usuario);

        $service = new Google_Service_Calendar($client);
        return $service->events->delete('primary', $eventoId);
    }

  
}
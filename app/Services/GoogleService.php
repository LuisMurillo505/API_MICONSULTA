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

/**
 * Obtiene y configura un cliente de Google autenticado para un usuario.
 *
 * Este método:
 * - Inicializa un cliente de Google usando las credenciales configuradas.
 * - Asigna el access token y refresh token almacenados del usuario.
 * - Verifica si el token de acceso ha expirado.
 * - Si está expirado, renueva el token usando el refresh token
 *   y actualiza los nuevos valores en la base de datos.
 *
 * Es utilizado principalmente para interactuar con servicios de Google
 * como Google Calendar en nombre del usuario.
 *
 * @param Usuario $usuario Usuario autenticado que tiene integración con Google
 *
 * @return \Google_Client Cliente de Google configurado y listo para usarse
 *
 * @throws \Exception Si ocurre un error durante la renovación del token
 */
    public function clienteGoogle(Usuario $usuario){

        
        $client = new Google_Client();

        // Configuración básica del cliente
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        // Asignar tokens actuales del usuario
        $client->setAccessToken([
            'access_token' => $usuario->google->google_token,
            'refresh_token' => $usuario->google->google_refresh_token,
            'expires_in' => $usuario->google->google_token_expires_in->diffInSeconds(now()),
        ]);

        // Renovar token si está expirado
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

/**
 * Determina el usuario que será el creador de eventos en Google Calendar
 * para una cita médica, priorizando al administrador de la clínica y,
 * en su defecto, al médico.
 *
 * Lógica:
 * - Si existe un administrador de la clínica con Google conectado,
 *   ese usuario será el creador del evento.
 * - Si además el médico tiene Google conectado, se agrega como invitado (attendee).
 * - Si el administrador no tiene Google, pero el médico sí, el médico será el creador.
 *
 * @param int $medico_id   ID del médico (personal) relacionado con la cita.
 * @param int $clinica_id  ID de la clínica a la que pertenece la cita.
 *
 * @return array
 * 
 * Retorna un arreglo con:
 * - usuarioCreador: Usuario que creará el evento en Google Calendar (o null).
 * - attendees: Lista de invitados al evento (correos electrónicos).
 */
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
            return [
                'usuarioCreador' => null,
                'attendees' => []
            ];
        }
    }
    
/**
 * Crea un evento en Google Calendar asociado a una cita médica.
 *
 * Este método genera un evento en el calendario del usuario creador (admin o médico),
 * asigna asistentes si corresponde y guarda la relación del evento con la cita
 * (ID del evento y propietario del calendario).
 *
 * @param  Citas  $cita
 *         Instancia de la cita que se sincronizará con Google Calendar.
 *
 * @param  array  $usuarioCreador
 *         Arreglo que contiene:
 *         - 'usuarioCreador' => Usuario que creará el evento en Google Calendar.
 *         - 'attendees' => Lista de asistentes (correos electrónicos).
 *
 * @param  string  $fecha
 *         Fecha de la cita en formato YYYY-MM-DD.
 *
 * @param  string  $hora_inicio
 *         Hora de inicio de la cita en formato H:i.
 *
 * @param  string  $hora_fin
 *         Hora de fin de la cita en formato H:i.
 *
 * @return Google_Service_Calendar_Event
 *         Evento creado en Google Calendar.
 *
 * @throws \Exception
 *         Puede lanzar excepciones relacionadas con autenticación,
 *         comunicación con la API de Google o errores de persistencia.
 */
    public function crearEventoGoogleCalendar(Citas $cita,array $usuarioCreador, $fecha,  $hora_inicio, $hora_fin)
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

/**
 * Elimina un evento existente de Google Calendar.
 *
 * Este método utiliza las credenciales de Google del usuario creador del evento
 * para autenticar la solicitud y eliminar el evento del calendario principal.
 *
 * @param string $eventoId
 *        ID del evento en Google Calendar que se desea eliminar.
 *
 * @param array $usuarioCreador
 *        Información del usuario creador del evento. Debe contener:
 *        - 'usuarioCreador' => Usuario (objeto o arreglo con el ID del usuario)
 *
 * @return void
 *
 * @throws \Exception
 *         Si el usuario no existe o no cuenta con un token válido de Google.
 */
     public function eliminarEvento($eventoId,array $usuarioCreador)
    {
        $usuario = Usuario::find($usuarioCreador['usuarioCreador']['id']);

        if (!$usuario || !$usuario->google) {
                throw new Exception("No se puede eliminar el evento: el usuario no tiene token válido");
        }

        // Crear cliente autenticado de Google
        $client=$this->clienteGoogle($usuario);

        // Servicio de Google Calendar
        $service = new Google_Service_Calendar($client);

        // Eliminar evento del calendario de google
        return $service->events->delete('primary', $eventoId);
    }

  
}
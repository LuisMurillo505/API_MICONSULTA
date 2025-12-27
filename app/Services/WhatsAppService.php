<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Citas;

class WhatsAppService
{
    protected $client;

    // public function enviarWhatsAppMedico($medico, $cita)
    // {

    //    $medico_citas = Citas::whereHas('personal.usuario.clinicas.suscripcion.plan',function($q) {
    //         $q->where('nombre','Estandar');
    //      })
    //     ->where('status_id',1)
    //     ->whereDate('fecha_cita', now()->toDateString())
    //     ->with('personal')
    //     ->get();         

    //     $token = env('WHATSAPP_TOKEN');
    //     $phoneId = env('WHATSAPP_PHONE_ID');
        
    //     Http::withToken($token)->post("https://graph.facebook.com/v22.0/{$phoneId}/messages", [
    //         "messaging_product" => "whatsapp",
    //         "to" => '52'.$medico->telefono,
    //         "type" => "template",
    //         "template" => [
    //             "name" => "notificarcitas_medico", // Nombre EXACTO de la plantilla en Meta
    //             "language" => [
    //                 "code" => "es_MX" // o el idioma que configuraste
    //             ],
    //             "components" => [
    //                 [
    //                     "type" => "body",
    //                     "parameters" => [
    //                         ["type" => "text", "text" => $medico->usuario->clinicas->nombre],
    //                         ["type" => "text", "text" => $cita->servicio->descripcion],
    //                         ["type" => "text", "text" => Carbon::parse($cita->fecha_cita ?? null)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')],
    //                         ["type" => "text", "text" => Carbon::parse($cita->hora_inicio)->format('h:i A')],
    //                         ["type" => "text", "text" => $cita->paciente->nombre.' '.$cita->paciente->apellido_paterno],
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ]);
    // }

/**
 * Envía un resumen diario de citas por WhatsApp a cada médico.
 *
 * - Solo aplica para clínicas con plan "Pro".
 * - Incluye citas activas (status_id = 1) del día actual.
 * - Agrupa las citas por médico.
 * - Envía un mensaje por médico usando una plantilla de WhatsApp Cloud API.
 *
 * Requisitos:
 * - Variables de entorno WHATSAPP_TOKEN y WHATSAPP_PHONE_ID configuradas.
 * - Plantilla "notificarcitas_resumen" aprobada en Meta.
 *
 * @return void
 * @throws \Exception
 */

    public function enviarWhatsAppMedico()
    {

       $medico_citas = Citas::whereHas('personal.usuario.clinicas.suscripcion.plan',function($q) {
            $q->where('nombre','Pro');
         })
        ->where('status_id',1)
        ->whereDate('fecha_cita', now()->toDateString())
        ->with(['personal.usuario.clinicas','servicio','paciente'])
        ->orderBy('hora_inicio')
        ->get(); 
        
        $token = env('WHATSAPP_TOKEN');
        $phoneId = env('WHATSAPP_PHONE_ID');

        $citasPorMedico = $medico_citas->groupBy('personal_id');

        foreach($citasPorMedico as $medicoId => $citas){
            $medico=$citas->first()->personal;
            $clinica=$medico->usuario->clinicas->nombre;

            $resumen = "";

            foreach($citas as $cita){
                 $resumen .= "• {$cita->servicio->descripcion} con {$cita->paciente->nombre} {$cita->paciente->apellido_paterno} a las " 
                    . Carbon::parse($cita->hora_inicio)->format('h:i A') . " | ";
            }

            $response=Http::withToken($token)->post("https://graph.facebook.com/v22.0/{$phoneId}/messages", [
                "messaging_product" => "whatsapp",
                "to" => '52'.$medico->telefono,
                "type" => "template",
                "template" => [
                    "name" => "notificarcitas_resumen", // Nombre EXACTO de la plantilla en Meta
                    "language" => [
                        "code" => "es_MX" // o el idioma que configuraste
                    ],
                    "components" => [
                        [
                            "type" => "body",
                            "parameters" => [
                                ["type" => "text", "text" => $clinica],
                                ["type" => "text", "text" => $resumen],
                            ]
                        ]
                    ]
                ]
            ]);

            // log::info($response->json());

        }
      
    }

    // public function enviarWhatsAppPaciente($cita)
    // {
    //     $token = env('WHATSAPP_TOKEN');
    //     $phoneId = env('WHATSAPP_PHONE_ID');
        
    //     Http::withToken($token)->post("https://graph.facebook.com/v22.0/{$phoneId}/messages", [
    //         "messaging_product" => "whatsapp",
    //         "to" => '52'.$cita->paciente->telefono,
    //         "type" => "template",
    //         "template" => [
    //             "name" => "notificarcitas_medico", // Nombre EXACTO de la plantilla en Meta
    //             "language" => [
    //                 "code" => "es_MX" // o el idioma que configuraste
    //             ],
    //             "components" => [
    //                 [
    //                     "type" => "body",
    //                     "parameters" => [
    //                         ["type" => "text", "text" => $cita->paciente->clinicas->nombre],
    //                         ["type" => "text", "text" => $cita->servicio->descripcion],
    //                         ["type" => "text", "text" => Carbon::parse($cita->fecha_cita ?? null)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')],
    //                         ["type" => "text", "text" => Carbon::parse($cita->hora_inicio)->format('h:i A')],
    //                         ["type" => "text", "text" => $cita->personal->nombre.' '.$cita->personal->apellido_paterno],
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ]);
    // }
}

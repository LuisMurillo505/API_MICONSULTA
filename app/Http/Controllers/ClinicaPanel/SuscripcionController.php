<?php

namespace App\Http\Controllers\ClinicaPanel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use App\Models\Planes;
use App\Models\Suscripcion;
use App\Models\Payment;
use App\Services\UsuarioService;
use App\Services\APIService;
use App\Services\SuscripcionService;
use Stripe\Stripe;
use Stripe\Subscription;
use Illuminate\Support\Facades\Log;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Stripe\Webhook;
use Illuminate\Support\Facades\Mail;

class SuscripcionController extends Controller
{

    protected $usuarioService;
    protected $suscripcionService;

    public function __construct(UsuarioService $usuarioService, SuscripcionService $suscripcionService){
        $this->usuarioService=$usuarioService;
        $this->suscripcionService=$suscripcionService;
    }
    
/**
 * Registra una nueva clínica y su usuario administrador.
 *
 * Flujo general:
 * - Valida los datos enviados desde el formulario.
 * - Verifica que el correo no esté registrado previamente.
 * - Obtiene el plan seleccionado.
 * - Registra el usuario y la clínica.
 * - Crea la suscripción asociada.
 * - Registra al médico administrador.
 * - Si el plan es gratuito, lo activa inmediatamente.
 * - Si el plan es de pago, prepara el cliente en Stripe.
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
    public function register(Request $request)
    {
        try {
            // Validar los datos del formulario
            $validated = $request->validate([
                'clinica' => 'required|string',
                'plan' => 'required|string',
                'direccion.calle' => 'required|string',
                'direccion.localidad' => 'required|string',
                'direccion.ciudad' => 'required|string',
                'telefono' => 'required|string',
                'correo' => 'required|email:rfc,dns',
                'password' => 'required|string',
                'confirm_password' => 'required|string|same:password',
                'nombre'=>'string',
                'apellido_paterno'=>'string',
                'apellido_materno'=>'string',
                'fecha_nacimiento'=>'date',
                'profesion'=>'string',
            ]);

            // Verificar si el usuario ya existe
            $check_usuario = Usuario::where('correo', '=', $request->correo)->first();
            if ($check_usuario) {
                return response()->json([
                    'success' => false,
                    'error'=>'usuario_existe',
                    'message'=>'Correo ya registrado, por favor inicie sesión.'
                ], 403);
            }

            // Obtener plan
            $plan = Planes::where('nombre', $request->plan)->first();
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'error'=>'plan_noExiste',
                    'message'=>'El plan seleccionado no es válido.'
                ], 403);
            }

            //registrar usuario en clinica
            $usuario=$this->usuarioService->registrarUsuarioClinica($validated);
            
            //Crear suscripcion
            $suscripcion=Suscripcion::create([
                'plan_id' => $plan->id,
                'clinica_id'=> $usuario->clinicas->id,
                'status_id' => 5, // Se activará después del pago
                'inicio_plan' => now(),
            ]);

            //Registro del médico administrador
            $adminMedico=$this->usuarioService->store_AdminMedico($validated,$usuario->id);

            // Si el plan es gratuito, activar de inmediato
            if ($plan->nombre === 'Gratuito') {
                //activar plan gratuito
                $this->suscripcionService->activarPlanGratis($suscripcion,$usuario);
                Mail::to($usuario->correo)->send(new \App\Mail\RegistroMail( $usuario,$plan));
                return response()->json([
                    'success'=>true,
                    'plan'=>$plan,
                    'usuario'=>$usuario
                ]);
            }

            //crear cliente en stripe
            $stripeCustomer=$this->suscripcionService->crearClienteStripe($usuario);

            // Retorna los datos en formato JSON
            return response()->json([
                'success'=>true,
                'plan'=>$plan,
                'usuario'=>$usuario
            ]);

        } catch (\Throwable $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   
        }
    }

/**
 * Inicia el proceso de Checkout con Stripe para un plan de suscripción.
 *
 * - Valida que el plan exista y tenga un price_id de Stripe
 * - Obtiene el usuario
 * - Crea u obtiene el cliente de Stripe
 * - Genera una sesión de Stripe Checkout
 * - Retorna la URL de pago al frontend
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
    public function checkout(Request $request)
    {
        try{
            // Cargar el plan desde la base de datos
            $plan = Planes::where('nombre',$request->plan)->first();

            //usuario
            $usuario=usuario::findOrFail($request->usuario_id);

            if (!$plan->stripe_price_id) {
                  return response()->json([
                    'success' => false,
                    'error'=>'plan_sinStripePriceID',
                    'message'=>'Este plan no tiene un ID de precio de Stripe configurado.'
                ], 403);
            }

            // Crear u obtener cliente de Stripe
            $stripeCustomer=$this->suscripcionService->crearClienteStripe($usuario);
            
            // Crear la sesión de Checkout
            $checkoutSession=$this->suscripcionService->checkoutSession($plan,$stripeCustomer);

            // Redirigir al usuario a Stripe Checkout
            return response()->json([
                'success'=>true,
                'url'=>$checkoutSession->url
            ]);

          } catch (\Throwable $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error en eñ checkout',
                'error' => $e->getMessage(),
            ], 500);   
        }
        
    }

/**
 * Maneja el flujo de éxito después de completar un pago en Stripe.
 *
 * - Obtiene el usuario que realizó el pago.
 * - Recupera la sesión de Stripe usando el session_id.
 * - Identifica el plan contratado.
 * - Cancela suscripciones activas anteriores del cliente.
 * - Guarda el pago en la base de datos.
 * - Activa la nueva suscripción.
 * - Envía correo de confirmación al usuario.
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 */
    public function exito(Request $request)
    {
       
        try {

            // Buscar al usuario por su Stripe Customer ID
            $usuario = Usuario::findOrFail($request->usuario_id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error'=>'usuario_Noexiste',
                    'message'=>'Usuario no encontrado'
                ], 403);
            }

            Stripe::setApiKey(config('services.stripe.secret'));

            $session_id = $request->query('session_id');

            if (!$session_id) {
                return response()->json([
                    'success' => false,
                    'error'=>'sessioID_noEncontrada',
                    'message'=>'No se encontró el ID de sesión.'
                ], 403);
            }
            //obtenemos datos de la suscripcion
            $session = $this->suscripcionService->stripeSession($session_id);

            $plan = Planes::where('stripe_price_id', $session['stripePriceId'])->first();

            if(!$plan){
                return response()->json([
                    'success' => false,
                    'error'=>'plan_noEncontrado',
                    'message'=>'Plan no encontrador'
                ], 403);
            }

            //Obtener todas las suscripciones activas del cliente
            $activeSubscriptions = Subscription::all([
                'customer' => $session['stripeCustomerId'],
                'status' => 'active',
                'limit' => 100,
            ]);

            //cancelar la anterior suscripcion
            foreach ($activeSubscriptions->data as $activeSub) {

                if ($activeSub->id !== $session['stripeSubscriptionId']) {
                    Subscription::retrieve($activeSub->id)->cancel();;
                }
            }
            //guardar pago 
            $this->suscripcionService->savePayment($session,$usuario,$plan);

            //activar suscripcion
            $this->suscripcionService->activarSuscripcion($session,$usuario,$plan);

            Mail::to($usuario->correo)->send(new \App\Mail\RegistroMail( $usuario,$plan));

            return response()->json([
                'success'=>true,
                'message'=>'Suscripción exitosa y plan activado.'
            ]);

        } catch (\Throwable $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error en la suscripcion',
                'error' => $e->getMessage(),
            ], 500);   
        }
    }


/**
 * Maneja el flujo cuando un usuario cancela el proceso de pago en Stripe.
 *
 * Si el usuario ya cuenta con algún pago previo, se notifica el error
 * y se solicita iniciar sesión nuevamente.
 * Si no existe ningún pago previo, se activa automáticamente el plan Gratuito.
 *
 * @param int $usuario_id ID del usuario que canceló el pago
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable Si ocurre un error inesperado durante el proceso
 */
    public function cancelado(int $usuario_id)
    {
        try{
            $usuario = Usuario::findOrFail($usuario_id);

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'error'=>'usuario_Noexiste',
                    'message'=>'Usuario no encontrado'
                ], 403);
            }

            //checar si ya hubo un pago o suscripcion gratuita antes
            $pago=Payment::where('clinica_id',$usuario->clinicas->id)->first();

            if($pago){
                return response()->json([
                    'success' => false,
                    'error'=>'error_pago',
                    'message'=>'Hubo un error en el pago, inicia sesion nuevamente.'
                ], 403);
            }
            
            $this->suscripcionService->activarPlanGratis($usuario->clinicas->suscripcion,$usuario);
            return response()->json([
                'success' => false,
                'error'=>'erro_pago',
                'message'=>'Hubo un error en el pago, plan Gratuito activado. Inicia sesion nuevamente'
            ], 403);

        }catch (\Throwable $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error en la suscripcion',
                'error' => $e->getMessage(),
            ], 500);   
        }
        
    }

/**
 * Cancela una suscripción activa en Stripe marcándola para terminar
 * al final del período actual.
 *
 * - Busca al usuario por ID.
 * - Obtiene la suscripción asociada a la clínica.
 * - Marca la suscripción de Stripe como `cancel_at_period_end = true`.
 * - Actualiza el estado local de la suscripción a "Por terminar".
 *
 * @param int $usuario_id ID del usuario que solicita la cancelación
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable Si ocurre un error inesperado
 */
    public function cancelarSuscripcion(int $usuario_id){
        try{
            $usuario=Usuario::findOrFail($usuario_id);

            $suscripcion_clinica=Suscripcion::where('clinica_id',$usuario->clinica_id)->first();

            if (!$suscripcion_clinica->stripe_subscription_id) {
                return response()->json([
                    'success' => false,
                    'error'=>'Suscripcion_NoEncontrada',
                    'message'=>'No se encontró una suscripción activa.'
                ], 403);
            }

            Stripe::setApiKey(config('services.stripe.secret'));

            $subscription = Subscription::update($suscripcion_clinica->stripe_subscription_id,[
                'cancel_at_period_end' =>true,
            ]);

            $suscripcion_clinica->status_id=6;
            $suscripcion_clinica->save();

            return response()->json([
                'success'=>true,
                'message'=>'Tu suscripción ha sido cancelada. Seguirá activa hasta el final del período actual.'
            ]);

        } catch (\Throwable $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   
        }
    }

/**
 * Maneja los eventos entrantes del webhook de Stripe.
 *
 * Este método:
 * - Verifica la firma del webhook para asegurar que el evento proviene de Stripe.
 * - Registra el tipo de evento recibido.
 * - Ejecuta la lógica correspondiente según el tipo de evento:
 *   - invoice.paid
 *   - invoice.payment_failed
 *   - customer.subscription.updated
 *   - customer.subscription.deleted
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
    public function handleWebhook(Request $request)
    {
        // Configurar la API Key de Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        // Secret del webhook configurado en Stripe
        $endpoint_secret = config('services.stripe.webhook_secret');
        // Contenido del request
        $payload = $request->getContent();
        // Header de firma enviado por Stripe
        $sig_header = $request->server('HTTP_STRIPE_SIGNATURE');
        $event = null;


        /**
         * Verificación de la firma del webhook
         * Esto garantiza que el evento realmente proviene de Stripe
         */
        try {
            $event = Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
             Log::info('Evento recibido: ' . $event->type);
        } catch (\UnexpectedValueException $e) {
            Log::error('Webhook payload inválido: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Webhook firma inválida: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        
        /**
         * Manejo de eventos de Stripe
         */
        switch ($event->type) {
           
            /**
             * Pago exitoso de una factura
             * Se registra el pago y se reactiva la suscripción
             */
            case 'invoice.paid':

                $this->suscripcionService->invoicePaid($event);
                break;
              
            /**
             * Fallo en el cobro de una factura
             * Se marca la suscripción como problemática y se notifica al usuario
             */ 
            case 'invoice.payment_failed':
               
                $this->suscripcionService->invoicePaymentFailed($event);
                break;

            /**
             * Actualización de la suscripción
             * Se usa para detectar cancelaciones al final del período
             */
            case 'customer.subscription.updated':
                
                $this->suscripcionService->customerSubscriptionUpdated($event);
                break;

            /**
             * Eliminación de una suscripción
             * Se valida si aún existen suscripciones activas antes de desactivar el plan
             */
            case 'customer.subscription.deleted':
                
                $this->suscripcionService->customerSubscriptionDeleted($event);
                break;

            /**
             * Eventos no manejados explícitamente
             */
            default:
                Log::info("Evento no manejado: " . $event->type);
        }

        // Respuesta estándar requerida por Stripe
        return response()->json(['status' => 'success']);
    }

    
}

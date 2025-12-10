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
                    'error'=>'usuario_existe'
                ], 403);
            }

            // Obtener plan
            $plan = Planes::where('nombre', $request->plan)->first();
            if (!$plan) {
                return response()->json([
                    'success' => false,
                    'error'=>'plan_noExiste'
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

    public function checkout(Request $request)
    {
        try{
            // Cargar el plan desde la base de datos
            $plan = Planes::where('nombre',$request->plan)->first();

            //usuario
            $usuario=auth()->user();

            if (!$plan->stripe_price_id) {
                return redirect()->back()->with('error', 'Este plan no tiene un ID de precio de Stripe configurado.');
            }

            // Crear u obtener cliente de Stripe
            $stripeCustomer=$this->suscripcionService->crearClienteStripe($usuario);
            
            // Crear la sesión de Checkout
            $checkoutSession=$this->suscripcionService->checkoutSession($plan,$stripeCustomer);

            // Redirigir al usuario a Stripe Checkout
            return redirect($checkoutSession->url);

          } catch (\Throwable $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   
        }
        
    }

    public function exito(Request $request)
    {
          // Buscar al usuario por su Stripe Customer ID
        $usuario = auth()->user();

        Stripe::setApiKey(config('services.stripe.secret'));

        $session_id = $request->query('session_id');

        if (!$session_id) {
            return redirect('/')->with('error', 'No se encontró el ID de sesión.');
        }

        try {

            //obtenemos datos de la suscripcion
            $session = $this->suscripcionService->stripeSession($session_id);


            $plan = Planes::where('stripe_price_id', $session['stripePriceId'])->first();

            if ($usuario) {

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

                // return redirect()->route('admin.index')->with('welcome', 'Suscripción exitosa y plan activado.');          
            }

            // return redirect()->route('login')->with('error', 'Usuario no encontrado.');

        } catch (\Throwable $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   
        }
    }
    public function cancelado()
    {
         $usuario = auth()->user();

            if ($usuario) {
                // $usuario->status_id = 1;
                // $usuario->save();

                $pago=Payment::where('clinica_id',$usuario->clinicas->id)->first();

                if($pago){
                    // return redirect()->route('login')->with('error', 'Hubo un error en el pago, Inicia sesion nuevamente');
                }
                
                $this->suscripcionService->activarPlanGratis($usuario->clinicas->suscripcion,$usuario);
                // return redirect()->route('login')->with('error', 'Hubo un error en el pago, plan Gratuito activado. Inicia sesion nuevamente');

                
            }
        // return view('login');
    }

    public function cancelar_suscripcion(){
        try{
            $usuario=auth()->user();

            $suscripcion_clinica=Suscripcion::where('clinica_id',$usuario->clinica_id)->first();


            if (!$suscripcion_clinica->stripe_subscription_id) {
                return back()->with('error', 'No se encontró una suscripción activa.');
            }

            Stripe::setApiKey(config('services.stripe.secret'));

            $subscription = Subscription::update($suscripcion_clinica->stripe_subscription_id,[
                'cancel_at_period_end' =>true,
            ]);

            $suscripcion_clinica->status_id=6;
            $suscripcion_clinica->save();

            // return back()->with('success', 'Tu suscripción ha sido cancelada. Seguirá activa hasta el final del período actual.');

        } catch (\Throwable $e) {
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al actualizar el usuario',
                'error' => $e->getMessage(),
            ], 500);   
        }
    }

     public function handleWebhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $endpoint_secret = config('services.stripe.webhook_secret');
        $payload = $request->getContent();
        $sig_header = $request->server('HTTP_STRIPE_SIGNATURE');
        $event = null;


        // Verificación de firma
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

        // Manejo de eventos
        switch ($event->type) {
           
            case 'invoice.paid':

                $this->suscripcionService->invoicePaid($event);
                break;
                       
            case 'invoice.payment_failed':
               
                $this->suscripcionService->invoicePaymentFailed($event);
                break;

            case 'customer.subscription.updated':
                
                $this->suscripcionService->customerSubscriptionUpdated($event);
                break;

            case 'customer.subscription.deleted':
                
                $this->suscripcionService->customerSubscriptionDeleted($event);
                break;

            default:
                Log::info("Evento no manejado: " . $event->type);
        }

        return response()->json(['status' => 'success']);
    }

    
}

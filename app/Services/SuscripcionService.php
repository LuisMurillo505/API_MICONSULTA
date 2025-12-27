<?php

namespace App\Services;

use Illuminate\Http\Request;
use Exception;
use App\Models\Planes;
use App\Models\Clinicas;
use App\Models\Suscripcion;
use App\Models\Payment;
use App\Services\PlanService;
use App\Models\StripeTarifas;
use Illuminate\Support\Facades\Mail;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Subscription;
use Illuminate\Support\Facades\Log;
use App\Models\Usuario;
use Stripe\Webhook;
use Stripe\Checkout\Session;


class SuscripcionService
{

    protected $stripeApiKey;
    protected $planService;
    public function __construct(PlanService $planService)
    {
        // Establecer la clave de API de Stripe
        $this->stripeApiKey = config('services.stripe.secret');  // Se obtiene desde la configuración
        Stripe::setApiKey($this->stripeApiKey);  // Establecer la clave globalmente

        $this->planService=$planService;
    }

/**
 * Verifica el estado de la suscripción asociada al usuario.
 *
 * Evalúa si la suscripción de la clínica del usuario está activa o vencida,
 * considerando el estado actual y los días restantes en relación al período
 * de espera definido por el plan.
 *
 * Si la suscripción ha superado el período de gracia, se actualiza su estado
 * a vencido automáticamente.
 *
 * @param  Usuario  $usuario  Usuario autenticado con relación a clínica y suscripción.
 * @return array {
 *     @type string $estado        Estado de la suscripción ('activo' | 'vencido').
 *     @type string $mensaje       Mensaje descriptivo cuando el plan está vencido.
 *     @type string $es_personal   Puesto del usuario si pertenece al personal.
 * }
 *
 * @throws \Exception Si ocurre un error durante la verificación.
 */
    public function verificarSuscripcion($usuario){
        try{
            $suscripcion = $usuario->clinicas->suscripcion ?? null;
            $plan=$suscripcion->plan;

            // Verificación de estado del plan
            if ($suscripcion) {
                $statusId = $suscripcion->status_id;

                if (in_array($statusId, [2,5]) || $suscripcion->dias_restantes < -($plan->dias_espera)) {
                    // Si el plan expiró, actualiza el estado si aplica
                    if ($suscripcion->getDiasRestantes() < -($plan->dias_espera) && $statusId !== 2) {
                        $suscripcion->status_id = 2;
                        $suscripcion->save();
                    }

                    return [
                        'estado' => 'vencido',
                        'mensaje' => 'Plan Vencido',
                        'es_personal' => $usuario->personal->puesto->descripcion ?? 'Personal Administrador',
                    ];
                }
            }

            return ['estado' => 'activo'];
        }catch(Exception $e){
            throw $e;
        }
    }

 /**
 * Crea o recupera un cliente en Stripe a partir de un usuario del sistema.
 *
 * - Busca si ya existe un cliente en Stripe usando el correo del usuario.
 * - Si existe, reutiliza el cliente encontrado.
 * - Si no existe, crea un nuevo cliente en Stripe con información de la clínica.
 * - Guarda el `stripe_customer_id` en la clínica asociada al usuario.
 *
 * @param  \App\Models\Usuario  $usuario
 *         Usuario autenticado que pertenece a una clínica.
 *
 * @return \Stripe\Customer
 *         Cliente de Stripe creado o recuperado.
 *
 * @throws \Exception
 *         Cuando ocurre un error al comunicarse con Stripe o al guardar en la base de datos.
 */    public function crearClienteStripe($usuario):Customer{
        try{
            $Customers = Customer::all([
                'email' => $usuario->correo,
                'limit' => 1,
            ]);

            if(!empty($Customers->data)){
                $stripeCustomer = $Customers->data[0];
            }else{
                $stripeCustomer = Customer::create([
                    'email' =>  $usuario->correo,
                    'name' => $usuario->clinicas->nombre,
                    'metadata' => [
                        'usuario_id' => $usuario->id,
                        'clinica_id' => $usuario->clinicas->id,
                    ],
                ]);
            }

            //Actualizar el campo stripe_customer_id en clinica
            $usuario->clinicas->stripe_customer_id= $stripeCustomer->id;
            $usuario->clinicas->save();

            return $stripeCustomer;
            
        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Crea una sesión de Checkout en Stripe para la suscripción a un plan.
 *
 * Este método genera una sesión de Stripe Checkout en modo suscripción,
 * asociando el cliente existente y el plan seleccionado. Incluye soporte
 * para códigos promocionales y define las URLs de éxito y cancelación
 * apuntando al frontend de la aplicación.
 *
 * Flujo general:
 * - Se crea una sesión de tipo "subscription".
 * - Se asigna el cliente de Stripe previamente creado.
 * - Se vincula el precio del plan mediante su `stripe_price_id`.
 * - Se habilitan códigos de promoción.
 * - Se configuran las URLs de redirección para éxito y cancelación.
 *
 * @param  mixed   $plan
 *         Objeto del plan que contiene el `stripe_price_id`.
 * @param  mixed   $stripeCustomer
 *         Cliente de Stripe (ID o instancia válida) asociado al usuario/clínica.
 *
 * @return \Stripe\Checkout\Session
 *         Sesión de Checkout creada en Stripe.
 *
 * @throws \Exception
 *         Si ocurre un error al crear la sesión en Stripe.
 */
    public function checkoutSession($plan, $stripeCustomer): Session{

        try{
            
            // Crear sesión de Checkout
            $checkoutSession = Session::create([
                'mode' => 'subscription',
                'customer' => $stripeCustomer,
                'line_items' => [[
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ]],
                'allow_promotion_codes' => true,
                // 'success_url' => config('app.web_url') .
                // route('suscripcion.exito', [], false) .
                // '?session_id={CHECKOUT_SESSION_ID}',
                // 'cancel_url' => config('app.web_url') .
                // route('suscripcion.cancelado', [], false),

                // 'success_url' => route('suscripcion.exito') . '?session_id={CHECKOUT_SESSION_ID}',
                // 'cancel_url' => route('suscripcion.cancelado'),

                'success_url' => rtrim(config('app.frontend_url'), '/') .
                    '/suscripcion/exito?session_id={CHECKOUT_SESSION_ID}',

                'cancel_url' => rtrim(config('app.frontend_url'), '/') .
                    '/suscripcion/cancelado',
            ]);

            return $checkoutSession;

        }catch(Exception $e){
            throw $e;
        }

    }

/**
 * Activa el plan gratuito para una clínica y su usuario principal.
 *
 * Este método asigna el plan gratuito a la suscripción indicada,
 * activa la suscripción y el usuario, establece la fecha de inicio del plan
 * y registra un pago con monto cero en el historial de pagos.
 *
 * Se utiliza normalmente cuando una clínica inicia por primera vez
 * o cuando se reactiva con un plan sin costo.
 *
 * @param  mixed   $suscripcion  Instancia de la suscripción asociada a la clínica
 * @param  mixed   $usuario      Usuario principal de la clínica
 * @return void
 *
 * @throws \Exception Si ocurre un error durante la activación o el guardado
 */
    public function activarPlanGratis($suscripcion,$usuario):void{
        try{
            
            $suscripcion->inicio_plan = now();
            $suscripcion->plan_id=1;
            $suscripcion->status_id = 1;
            $usuario->status_id = 1;
            $suscripcion->save();
            $usuario->save();
            Payment::create([
                'clinica_id'=>$usuario->clinicas->id,
                'plan_id'=>$suscripcion->plan_id,
                'invoiceNumber'=>$usuario->clinicas->nombre.'-'.$usuario->clinicas->id,
                'discount'=>null,
                'payment_id' => null,
                'amount' => 0,
                'status' => 'paid',
                'date' => now(),
            ]);
        }catch(Exception $e){
            throw $e;
        }
       
    }

/**
 * Obtiene y procesa la información de una sesión de Stripe Checkout.
 *
 * A partir del ID de la sesión, recupera la suscripción asociada,
 * la factura más reciente y los datos del pago realizado,
 * incluyendo monto, estatus, descuento y fechas.
 *
 * @param string $session_id ID de la sesión de Stripe Checkout.
 *
 * @return array{
 *     stripeSubscriptionId: string,
 *     stripePriceId: string,
 *     invoiceNumber: string|null,
 *     descuento: float|null,
 *     montoPagado: float,
 *     status: string,
 *     fechaPago: string,
 *     stripeCustomerId: string
 * }
 *
 * @throws \Exception Si ocurre un error al comunicarse con Stripe
 *                    o al obtener la información de la sesión.
 */
    public function stripeSession($session_id):array{
        try{
             // Obtener detalles de la sesión desde Stripe
            $session = Session::retrieve($session_id);

            $stripeSuscripcionId=$session->subscription;
            $subscription = Subscription::retrieve($session->subscription);
            $stripePriceId = $subscription->items->data[0]->price->id;

            //Datos del pago
            $latestInvoiceId = $subscription->latest_invoice;
            $invoice = \Stripe\Invoice::retrieve($latestInvoiceId);
        
            // Número de factura
            $invoiceNumber = $invoice->number;

            // ID del pago
            // $paymentIntentId = $invoice->payment_intent;
            // $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

            //cupon aplicado
            if ($session->total_details && $session->total_details->amount_discount > 0) {
                // Cupón aplicado
                $descuento = $session->total_details->amount_discount / 100;
            }
          

            // Obtener el monto pagado y el estatus de la factura
            $montoPagado = $invoice->amount_paid / 100;
            $status = $invoice->status;

            //fecha pago
            $fechaPago = date('Y-m-d H:i:s', $invoice->status_transitions->paid_at);

            //id del cliente
            $stripeCustomerId = $session->customer;

            $data=[
                'stripeSubscriptionId'=>$stripeSuscripcionId,
                'stripePriceId' => $stripePriceId,
                'invoiceNumber' => $invoiceNumber,
                'descuento'=>$descuento ?? null,
                'montoPagado'=>$montoPagado,
                'status' => $status,
                'fechaPago' => $fechaPago,
                'stripeCustomerId' => $stripeCustomerId
            ];

            return  $data;


        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Calcula la comisión de Stripe, el IVA correspondiente y el monto neto recibido.
 *
 * Este método obtiene la tarifa activa de Stripe desde la base de datos y calcula:
 * - La comisión de Stripe (porcentaje + monto fijo).
 * - El IVA aplicado a la comisión.
 * - El monto neto que recibe la clínica después de comisiones.
 *
 * @param float $monto Monto bruto del pago realizado por el cliente.
 *
 * @return array{
 *     net_amount: float,   // Monto neto después de comisiones e IVA
 *     comision: float,     // Total de comisión + IVA
 *     tarifa_id: int       // ID de la tarifa de Stripe utilizada
 * }
 *
 * @throws \Exception Si ocurre un error al calcular la tarifa.
 */
    public function calcularTarifa($monto){
        try{
            $tarifa=StripeTarifas::where('status_id','1')->first();
            $comisionStripe=$monto*($tarifa->porcentaje / 100)+$tarifa->fijo;
            $iva=$comisionStripe * ($tarifa->iva / 100); 
            $net_amount=$monto-($comisionStripe+$iva);

            $data=[
                'net_amount'=>$net_amount,
                'comision'=>($comisionStripe+$iva),
                'tarifa_id'=>$tarifa->id
            ];

            return $data;

        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Guarda un pago en la base de datos si no existe previamente
 * y actualiza el estado del usuario.
 *
 * Este método:
 * - Verifica si la factura ya fue registrada usando el invoiceNumber
 * - Calcula la comisión y el monto neto
 * - Registra el pago en la tabla payments
 * - Activa al usuario después del pago
 *
 * @param array|null $session  Datos obtenidos desde Stripe (factura, monto, descuento, etc.)
 * @param mixed      $usuario  Usuario autenticado que realizó el pago
 * @param mixed      $plan     Plan asociado al pago
 *
 * @return void
 *
 * @throws \Exception
 */

    public function savePayment(?array $session,$usuario,$plan){
        try{
            $factura=Payment::Where('invoiceNumber',$session['invoiceNumber'])->first();
            if(!$factura){
                $tarifa=$this->calcularTarifa($session['montoPagado']);

                Payment::create([
                    'clinica_id'=>$usuario->clinicas->id,
                    'plan_id'=>$plan->id,
                    'tarifa_id'=>$tarifa['tarifa_id'],
                    'invoiceNumber'=>$session['invoiceNumber'],
                    'discount'=>$session['descuento'],
                    'payment_id' => null,
                    'amount' => $session['montoPagado'],
                    'comision'=>$tarifa['comision'],
                    'net_amount'=>$tarifa['net_amount'],
                    'status' => $session['status'],
                    'date' => $session['fechaPago'],
                ]);
            }
             
            $usuario->status_id = 1;
            $usuario->save();
        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Activa una suscripción de pago para la clínica del usuario.
 *
 * Este método:
 * - Actualiza los límites del sistema según el nuevo plan
 *   (usuarios, servicios y archivos).
 * - Asigna el nuevo plan a la suscripción de la clínica.
 * - Guarda el ID de la suscripción de Stripe.
 * - Marca la suscripción como activa.
 * - Establece la fecha de inicio del plan.
 *
 * @param array|null $session  Datos obtenidos de Stripe (ID de suscripción, etc.)
 * @param mixed      $usuario  Usuario autenticado asociado a la clínica
 * @param mixed      $plan     Plan contratado
 *
 * @return void
 *
 * @throws \Exception
 */
    public function activarSuscripcion(?array $session, $usuario,$plan){
        try{
            $suscripcion_clinica=Suscripcion::find($usuario->clinicas->suscripcion->id);

            $this->planService->desactualizar_usuarios($usuario->clinicas->id,$plan->id);
            $this->planService->desactualizar_servicios($usuario->clinicas->id,$plan->id);
            $this->planService->desactualizar_archivos($usuario->clinicas->id,$plan->id);
            $suscripcion_clinica->plan_id=$plan->id;
            $suscripcion_clinica->stripe_subscription_id = $session['stripeSubscriptionId'];
            $suscripcion_clinica->status_id = 1;
            $suscripcion_clinica->inicio_plan = now();
            $suscripcion_clinica->save();

        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Maneja el evento invoice.paid de Stripe.
 *
 * Registra el pago en la base de datos, calcula comisiones,
 * activa la suscripción de la clínica y actualiza su estado.
 *
 * @param object $event Evento recibido desde Stripe (webhook)
 * @return void
 *
 * @throws \Exception
 */
    public function invoicePaid($event){
        try{
            $invoice = $event->data->object;

           foreach ($invoice->lines->data as $lineItem) {
                // Verificar que existan los campos antes de acceder
                if (isset($lineItem->pricing) && isset($lineItem->pricing->price_details)) {
                    $priceId = $lineItem->pricing->price_details->price;
                } 
            }
            $plan=Planes::where('stripe_price_id',$priceId)->first();

            $stripe_customer_id = $invoice->customer;

            $clinica = Clinicas::where('stripe_customer_id', $stripe_customer_id)->first();

            if ($clinica) {
                $tarifa=$this->calcularTarifa($invoice->amount_paid / 100,);
                Payment::create([
                'clinica_id'=>$clinica->id,
                'tarifa_id'=>$tarifa['tarifa_id'],
                'plan_id'=>$plan->id,
                'invoiceNumber'=>$invoice->number,
                'payment_id' => null,
                'amount' => $invoice->amount_paid / 100,
                'comision'=>$tarifa['comision'],
                'net_amount'=>$tarifa['net_amount'],
                'status' => $invoice->status,
                'date' =>  date('Y-m-d H:i:s', $invoice->status_transitions->paid_at),
            ]);
                $clinica->suscripcion->inicio_plan = now(); 
                $clinica->suscripcion->status_id = 1; 
                $clinica->suscripcion->save();

            } else {
                Log::warning("Usuario no encontrado con customer ID: $stripe_customer_id en invoice.paid.");
            }
            
        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Maneja el evento de Stripe cuando un pago de factura falla (invoice.payment_failed).
 *
 * - Obtiene la factura desde el evento de Stripe.
 * - Localiza la clínica asociada al customer de Stripe.
 * - Busca al usuario administrador de la clínica.
 * - Actualiza el estado de la suscripción a "pago fallido".
 * - Envía un correo notificando el problema de pago.
 *
 * @param object $event Evento recibido desde el webhook de Stripe
 * @return void
 *
 * @throws \Exception
 */

    public function invoicePaymentFailed($event){
        try{
            $invoice = $event->data->object;
            $stripe_customer_id = $invoice->customer;
            
            $clinica = Clinicas::where('stripe_customer_id', $stripe_customer_id)->first();
            $usuario=Usuario::where('clinica_id',$clinica->id)
            ->whereDoesntHave('personal')
            ->first();
            if(!$usuario){
                $usuario=Usuario::where('clinica_id',$clinica->id)
                ->whereHas('personal.puesto',function($q) {
                    $q->where('descripcion','Personal Administrador');
                })->first();
            }

            if($clinica){
                // Log::info("Usuario encontrado con customer ID: $stripe_customer_id en invoice.payment_failed.");

                $clinica->suscripcion->status_id=5;
                $clinica->suscripcion->save();
                Mail::to($usuario->correo)->send(new \App\Mail\ProblemaPagoMail( $clinica));

            } else {
                Log::warning("Usuario no encontrado con customer ID: $stripe_customer_id en invoice.paid.");
            }
            
        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Maneja el evento customer.subscription.updated de Stripe.
 *
 * Este método se ejecuta cuando una suscripción es actualizada en Stripe.
 * Actualmente valida si la suscripción fue marcada para cancelarse
 * al final del período pero aún sigue activa.
 *
 * En ese caso, se actualiza el estado de la suscripción de la clínica
 * a "Por Terminar".
 *
 * @param object $event Evento recibido desde el webhook de Stripe
 * @return void
 *
 * @throws \Exception
 */
    public function customerSubscriptionUpdated($event){
        try{
            $invoice = $event->data->object;
            $stripe_customer_id = $invoice->customer;
            
            $clinica = Clinicas::where('stripe_customer_id', $stripe_customer_id)->first();

            if($clinica){
                Log::info("Usuario encontrado con customer ID: $stripe_customer_id.");

                // Verificamos si la suscripción está cancelada pero aún activa
                if (
                    $invoice->cancel_at_period_end === true &&
                    $invoice->status === 'active'
                ) {
                    $clinica->suscripcion->status_id = 6;
                    $clinica->suscripcion->save();

                    // Log::info("La suscripción está activa pero cancelada al final del período. Estado actualizado a 'Por Terminar'.");
                } else {
                    Log::info("La suscripción sigue activa y no ha sido cancelada. No se actualiza el estado.");
                }
            } else {
                Log::warning("Usuario no encontrado con customer ID: $stripe_customer_id.");
            }
            
        }catch(Exception $e){
            throw $e;
        }
    }

/**
 * Maneja el evento customer.subscription.deleted de Stripe.
 *
 * Se ejecuta cuando una suscripción es eliminada en Stripe.
 * Verifica si el cliente aún tiene suscripciones activas:
 *  - Si tiene alguna activa, mantiene la suscripción de la clínica como activa.
 *  - Si no tiene ninguna activa, marca la suscripción como vencida
 *    y notifica al administrador de la clínica por correo.
 *
 * @param object $event Evento recibido desde el webhook de Stripe
 * @return void
 * @throws \Exception
 */

     public function customerSubscriptionDeleted($event){
        try{
           $invoice = $event->data->object;
           $stripe_customer_id = $invoice->customer;
            
            $clinica = Clinicas::where('stripe_customer_id', $stripe_customer_id)->first();
            $usuario=Usuario::where('clinica_id',$clinica->id)
            ->whereDoesntHave('personal')
            ->first();
            if(!$usuario){
                $usuario=Usuario::where('clinica_id',$clinica->id)
                ->whereHas('personal.puesto',function($q) {
                    $q->where('descripcion','Personal Administrador');
                })->first();
            }

            if($clinica){
                Log::info("Usuario encontrado con customer ID: $stripe_customer_id.");

                //Obtener todas las suscripciones activas del cliente
                $Subscriptions = Subscription::all([
                    'customer' => $stripe_customer_id,
                    'status' => 'active',
                    'limit' => 100,
                ]);

                if(count($Subscriptions->data) > 0){
                    $clinica->suscripcion->status_id=1;
                    $clinica->suscripcion->save();
                }else{
                    $clinica->suscripcion->status_id=2;
                    $clinica->suscripcion->save();
                    Mail::to($usuario->correo)->send(new \App\Mail\SuscripcionCanceladaMail( $clinica));

                }

            } else {
                Log::warning("Usuario no encontrado con customer ID: $stripe_customer_id.");
            }
            
        }catch(Exception $e){
            throw $e;
        }
    }



    
   

}
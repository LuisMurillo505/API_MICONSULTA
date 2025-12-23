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

    //obtiene o crea un nuevo cliente en stripe
    public function crearClienteStripe($usuario):Customer{
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

    //Manda al checkout de stripe para completar el pago
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

    //activa el plan gratuito
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

    //obtiene datos de la session de stipe
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
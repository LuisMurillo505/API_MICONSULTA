<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use App\Models\StripeTarifas;

class TarifaStripeContoller extends Controller
{
/**
 * Crea una nueva tarifa de Stripe en el sistema.
 *
 * Este método valida los datos recibidos de la solicitud, desactiva las tarifas existentes
 * y luego registra una nueva tarifa con el porcentaje, monto fijo e IVA especificados.
 * Retorna una respuesta JSON indicando el éxito o el error de la operación.
 *
 * @param  \Illuminate\Http\Request  $request  Objeto que contiene los datos de la nueva tarifa.
 * @return \Illuminate\Http\JsonResponse        Respuesta JSON indicando el resultado de la operación.
 */
     public function store(Request $request){
        try{
            //Validación de los datos recibidos
            $validated=$request->validate([
                'porcentaje'=>'required|numeric',
                'fijo'=>'required|numeric',
                'iva'=>'required|numeric'
            ]);

            //Desactiva todas las tarifas actuales antes de crear una nueva
            $this->desactivarTarifas();

            //Creación de la nueva tarifa con los valores validados
            StripeTarifas::create([
                'porcentaje'=>$validated['porcentaje'],
                'fijo'=>$validated['fijo'],
                'iva'=>$validated['iva'],
                'status_id'=>1,
                'created_at'=>now(),
                'updated_at'=>now()
            ]);

            // Retorna los datos en formato JSON
            return response()->json([
                'success'=>true,
            ]);

        }catch(Exception $e){
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al crear el plan',
                'error' => $e->getMessage(),
            ], 500);         
         }
    }

/**
 * Desactiva todas las tarifas de Stripe existentes.
 *
 * Este método recorre todas las tarifas registradas en la base de datos
 * y actualiza su estado (`status_id`) a inactivo (valor = 2).
 * Se utiliza antes de crear una nueva tarifa activa, garantizando que solo
 * exista una tarifa activa a la vez.
 *
 * @return \Illuminate\Http\JsonResponse Respuesta JSON indicando el resultado de la operación.
 */
    private function desactivarTarifas(){
         try{
            // Obtiene todas las tarifas de Stripe registradas y actualiza su estado 
            StripeTarifas::query()->update(['status_id' => 2, 'updated_at' => now()]);

           // Retorna los datos en formato JSON
            return response()->json([
                'success'=>true,
            ]);

        }catch(Exception $e){
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
             return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al crear el plan',
                'error' => $e->getMessage(),
            ], 500);          }
    }

/**
 * Actualiza el estado de una tarifa de Stripe a activa.
 *
 * Este método primero desactiva todas las tarifas existentes mediante la función `desactivarTarifas()`,
 * asegurando que solo una tarifa pueda estar activa a la vez. Luego, activa la tarifa específica 
 * identificada por el parámetro `$tarifa_id`.
 *
 * @param int $tarifa_id  ID de la tarifa que se desea activar.
 * @return \Illuminate\Http\JsonResponse Respuesta JSON indicando el resultado de la operación.
 */
    public function update($tarifa_id){
        try{
             //Desactiva todas las tarifas actuales antes de activar una nueva
            $this->desactivarTarifas();

            //Busca la tarifa por su ID
            $tarifa=StripeTarifas::find($tarifa_id);

            //Actualiza el estado de la tarifa a activa (status_id = 1)
            $tarifa->update([
                'status_id'=>1,
                'updated_at'=>now()
            ]);

            // Retorna los datos en formato JSON
            return response()->json([
                'success'=>true,
            ]);

        }catch(Exception $e){
            // Manejo de errores: retorna mensaje descriptivo con el detalle de la excepción.
             return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al crear el plan',
                'error' => $e->getMessage(),
            ], 500);         }
    }
}

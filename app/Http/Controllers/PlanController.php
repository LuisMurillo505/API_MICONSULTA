<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use App\Models\Planes;
use App\Models\Funciones;
use App\Models\Funciones_planes;

class PlanController extends Controller
{

/**
 * Crea un nuevo plan junto con sus funciones asociadas.
 *
 * Este método valida los datos recibidos, registra un nuevo plan en la base de datos
 * y crea las relaciones correspondientes con las funciones del sistema. 
 * Algunas funciones son opcionales y solo se crean si el valor está presente.
 *
 * @param \Illuminate\Http\Request $request
 *     Solicitud HTTP con los datos del plan a crear.  
 *     Campos esperados:
 *     - nombre: string (obligatorio)
 *     - duracion: int (obligatorio)
 *     - dias_espera: int (obligatorio)
 *     - precio: float (obligatorio)
 *     - stripe_price_id: string (obligatorio)
 *     - cantidades: array (opcional) — valores de cantidad por función.
 *
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Illuminate\Validation\ValidationException
 *     Si la validación de datos falla.
 * @throws \Exception
 *     Si ocurre un error durante la creación del plan o sus funciones.
 *
 */
    public function store(Request $request){
        try{

             /**
             *  Validación de los datos recibidos.
             * Laravel lanzará una excepción automáticamente si algo no cumple las reglas.
             */
            $validated=$request->validate([
                'nombre'=>'string|required',
                'duracion'=>'numeric|required',
                'dias_espera'=>'numeric|required',
                'precio'=>'numeric|required',
                'stripe_price_id'=>'string|required',
                'cantidades' => 'array',
                'cantidades.*' => 'nullable',
            ]);

            //Creación del nuevo plan en la base de datos.
            $plan=Planes::create([
                'nombre'=>$validated['nombre'],
                'duracion_dias'=>$validated['duracion'],
                'dias_espera'=>$validated['dias_espera'],
                'precio'=>$validated['precio'],
                'stripe_price_id'=>$validated['stripe_price_id'],
                'created_at'=>now(),
                'updated_at'=>now()  
            ]);

             /**
             * Obtiene todas las funciones disponibles para asignarlas al plan.
             * También define un arreglo con funciones opcionales (por ID),
             * que solo se registrarán si se proporcionó valor.
             */
            $funciones=Funciones::all();
            $funcionesOpcionales=[6]; // IDs de funciones opcionales

            /**
             *  Recorre cada función y crea su relación con el plan.
             * Si la función es opcional, solo se crea si hay valor en la solicitud.
             */
            foreach($funciones as $funcion){
                $valor = $validated['cantidades'][$funcion->id] ?? null;

                // Si la función es opcional
                if (in_array($funcion->id,$funcionesOpcionales)) {
                    if (!empty($valor)) {
                        Funciones_planes::create([
                            'plan_id' => $plan->id,
                            'funcion_id' => $funcion->id,
                            'cantidad' => 1,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } 
                // Si la función no es opcional, siempre se crea el registro
                else {
                    Funciones_planes::create([
                        'plan_id' => $plan->id,
                        'funcion_id' => $funcion->id,
                        'cantidad' => $valor, 
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        
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
 * Actualiza los datos de un plan existente.
 *
 * Este método recibe el ID del plan y una solicitud HTTP con los nuevos valores
 * para los campos `stripe_price_id`, `precio` y `dias_espera`.  
 * Valida los datos, busca el plan correspondiente en la base de datos y actualiza sus valores.
 * En caso de éxito, devuelve una respuesta JSON indicando éxito; de lo contrario, 
 * retorna un mensaje de error con el detalle de la excepción.
 *
 * @param  int  $plan_id  ID del plan que se desea actualizar.
 * @param  \Illuminate\Http\Request  $request  Objeto de la solicitud HTTP que contiene los datos validados.
 * @return \Illuminate\Http\JsonResponse  Respuesta JSON con el estado de la operación.
 */
    public function update($plan_id,Request $request){
        try{
            //Validación de los datos del request
            $validated=$request->validate([
                'stripe_price_id'=>'required|string',
                'precio'=>'required|numeric',
                'dias_espera'=>'required|numeric'
            ]); 

            // Búsqueda del plan en la base de datos
            $plan=Planes::find($plan_id);

            // Actualización del plan con los datos validados
            $plan->update([
                'stripe_price_id'=>$validated['stripe_price_id'],
                'precio'=>$validated['precio'],
                'dias_espera'=>$validated['dias_espera']
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
 * Crea una nueva función dentro del sistema.
 *
 * Este método valida los datos enviados en la solicitud HTTP y registra
 * una nueva función en la base de datos.  
 * Cada función representa una característica o capacidad que puede estar asociada a un plan.
 *
 * @param  \Illuminate\Http\Request  $request  Objeto de la solicitud HTTP con los datos de la nueva función.
 * @return \Illuminate\Http\JsonResponse  Respuesta JSON indicando el resultado de la operación.
 */
    public function storeFunciones(Request $request){
        try{
            //Validación de los datos recibidos
            $validated=$request->validate([
                'nombre'=>'required|string',
                'descripcion'=>'required|string'
            ]);

            //Creación del nuevo registro en la tabla "funciones"
            Funciones::create([
                'nombre'=>$validated['nombre'],
                'descripcion'=>$validated['descripcion'],
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
 * Actualiza la cantidad asociada a una función dentro de un plan.
 *
 * Este método recibe el ID de la relación `Funciones_planes` y los datos enviados
 * en la solicitud HTTP para actualizar el campo `cantidad`.  
 * Si la función existe, se actualiza con el nuevo valor; en caso contrario,
 * se devuelve un error en formato JSON.
 *
 * @param  \Illuminate\Http\Request  $request  Objeto de la solicitud HTTP con los datos enviados.
 * @param  int  $funcion_id  ID del registro en la tabla `funciones_planes` que se desea actualizar.
 * @return \Illuminate\Http\JsonResponse  Respuesta JSON con el estado de la operación.
 */ 
    public function updateFuncion(Request $request,$funcion_id){
        try{

            // Validación de los datos recibidos
            $validated=$request->validate([
                'cantidad'=>'nullable|integer'
            ]);

            // Buscar la relación de función dentro del plan
            $funcion=Funciones_planes::find($funcion_id);

            //Actualizar el campo "cantidad" en el registro encontrado
            $funcion->update([
                'cantidad'=>$validated['cantidad']
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
    
}

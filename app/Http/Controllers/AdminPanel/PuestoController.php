<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use App\Models\Puesto;

class PuestoController extends Controller
{

    
    /**
     * Crea un nuevo puesto con estado activo.
     */
    public function store(Request $request){

        try{
            // Validar la descripción del puesto
            $validated=$request->validate([
                'descripcionP'=>'required|string'
            ]);

            // Crear nuevo puesto
            Puesto::create([
                'descripcion'=>$validated['descripcionP'],
                'estado'=>'activo',
                'created_at'=>now(),
                'updated_at'=>now()
            ]);
            
            return response()->json([
                'success'=>true,
                'message'=>'Puesto creado correctamente'
            ]);

        }catch(\Exception $e){
            // Errores generales
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error.',
                'error' => $e->getMessage(),
            ], 500);
        }
       
    }

     /**
     * Cambia el estado del puesto entre activo e inactivo.
     */
    public function update(int $id){
        try{
            $puesto=Puesto::find($id);
            if($puesto->estado=='activo'){
                $puesto->estado='inactivo';
                $puesto->save();
                return response()->json([
                    'success'=>true,
                    'message'=>'Puesto actualizado correctamente'
                ]);
            }else{
                $puesto->estado='activo';
                $puesto->save();
                return response()->json([
                    'success'=>true,
                    'message'=>'Puesto actualizado correctamente'
                ]);       
            } 
        }catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina un puesto por su ID.
     */
    public function delete(int $id){
        try{
            $puesto=Puesto::find($id);
            if ($puesto){
                $puesto->delete();
                return response()->json([
                    'success'=>true,
                    'message'=>'Puesto eliminado correctamente'
                ]);    
            }
           
        }catch(\Exception $e){
             return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

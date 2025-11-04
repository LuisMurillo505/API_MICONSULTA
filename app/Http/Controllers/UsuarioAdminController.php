<?php

namespace App\Http\Controllers;

use App\Models\UsuarioAdmin;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Hash;

class UsuarioAdminController extends Controller
{
    public function store(Request $request){
        try{

            // Validación de los campos de entrada

            if($request->password!=$request->confirm_password){
                return back()->withInput()->with('error', 'Las contraseñas no coinciden');        
            }

            $check_usuario=UsuarioAdmin::where('correo','=',$request->correo)->first();

            if($check_usuario){
                return back()->withInput()->with('error', 'Correo ya existe');
            } 

            UsuarioAdmin::create([
                'correo' => $request->correo,
                'password' => Hash::make($request->password),  
                'status_id'=>1,
                'last_connection'=>null,
                'created_at'=>now(),
                'update_at'=>now()
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

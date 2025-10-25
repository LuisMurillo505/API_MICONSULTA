<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Clinicas;
use App\Models\ArchivosPaciente;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Validator;
use Exception;

class AuthController extends Controller
{

    public function index()
    {
        $usuarios = Usuario::all();
        return response()->json($usuarios);
    }
    public function puedeSubirArchivosPacientes($clinica_id, $paciente_id)
    {
        try {
            // Obtener plan con la función 5
            $archivosPermitidos = Clinicas::with(['suscripcion.plan.funciones_planes' => function ($query) {
                $query->where('funcion_id', 5);
            }])
            ->where('id', $clinica_id)
            ->whereHas('suscripcion.plan.funciones_planes', function ($q) {
                $q->where('funcion_id', 5);
            })
            ->first();

            if (!$archivosPermitidos) {
                return response()->json([
                    'error' => 'No se encontró la clínica o no tiene configurado el plan correctamente.',
                ], 404);
            }

            // Conteo de archivos del paciente en esa clínica
            $conteoArchivos = ArchivosPaciente::whereHas('paciente', function ($q) use ($clinica_id) {
                $q->where('clinica_id', $clinica_id);
            })
            ->where('paciente_id', $paciente_id)
            ->count();

            $limite = $archivosPermitidos->suscripcion->plan->funciones_planes->cantidad ?? null;

            return response()->json([
                'puede_subir' => is_null($limite) || $limite > $conteoArchivos,
                'limite'      => $limite,
                'subidos'     => $conteoArchivos,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error'   => 'Ocurrió un error al procesar la solicitud.',
                'mensaje' => $e->getMessage(),
            ], 500);
        }
    }
    //Registro de un nuevo usuario
    public function Register(Request $request){

        $validator=Validator::make($request->all(),[
            'nombre_usuario'=>'required|string|max:50',
            'password'=>'required|string|min:8|confirm',
            'email'=>'required|email'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        //crear usuario
        $user=Usuario::create([
            'nombre_usuario'=>$request->nombre_usuario,
            'password'=>Hash::make($request->password),
            'created_at'=>now(),
            'updated_at'=>now()
        ]);

        //generar un token
        $token=$user->createToken('UsuarioTeorosi')->plainTextToken;

        return response()->json([
            'message'=>'Usuario Creado Con Exito',
            'Usuario'=>$user,
            'token'=>$token
        ]);

    }

    //Login de usuario
    public function Login(Request $request){
        
        $validator=Validator::make($request->all(),[
            'correo'=>'required|email',
            'password'=>'required|string'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        if(auth()->attempt(['correo'=>$request->correo,'password'=>$request->password])){
            $user=auth()->user();
            $token=$user->createToken('UsuarioTerosi')->plainTextToken;

            return response()->json([
                'message'=>'Login con Exito',
                'user'=>$user,
                'token'=>$token
            ]);
        }

        return response()->json(['message'=>'No autorizado',401]);
    }

    //Logout
    public function Logout(Request $request){

        $request->user()->tokens->each(function($token){
            $token->delete();
        });

        return response()->json(['message'=>'Se Cerro Sesión']);
    }
}

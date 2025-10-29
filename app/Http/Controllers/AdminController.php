<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Clinicas;
use App\Models\Especialidad;
use App\Models\Payment;
use App\Models\Status;
use App\Models\Puesto;
use App\Models\Disponibilidad;
use App\Models\Personal;
use App\Models\ArchivosPaciente;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Validator;
use Exception;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Citas;
use App\Models\Pacientes;
use App\Models\UsuarioAdmin;
use App\Models\Servicio;
use App\Models\Planes;
use App\Models\StripeTarifas;
use App\Services\EconomiaService;


class AdminController extends Controller
{
    protected $economiaService;

    public function __construct(EconomiaService $economiaService){
        $this->economiaService=$economiaService;
    }

    public function conteoDatos()
    {
        try {

            $conteoClinicas = Clinicas::with('suscripcion')->count();
            $conteoCitasHoy = Citas::whereDate('fecha_cita', Carbon::today())->count();
            $conteoPacientesHoy = Pacientes::whereDate('created_at', Carbon::today())->count();
            $conteoUsuarios = Usuario::count();
            $conteoUsuariosAdmin = UsuarioAdmin::count();
            $conteoPacientes = Pacientes::count();
            $conteoServicios = Servicio::count();
            $conteoCitas = Citas::count();
            $conteoPlanes = Planes::count();
            $conteoTarifaStripe = StripeTarifas::count();

            return response()->json([
                'success' => true,
                'data' => compact(
                    'conteoClinicas',
                    'conteoCitasHoy',
                    'conteoPacientesHoy',
                    'conteoUsuarios',
                    'conteoUsuariosAdmin',
                    'conteoPacientes',
                    'conteoServicios',
                    'conteoCitas',
                    'conteoPlanes',
                    'conteoTarifaStripe'
                )
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener conteos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //obtiene informacion de las clinicas y sus suscripciones
    public function index_clinicas(){
        try{
            $clinicas = Clinicas::with('suscripcion.status','suscripcion.plan')->get();
            $suscripciones = Planes::withCount(['suscripcion as total_clinicas' => function ($query) {
                $query->where('status_id', 1);
            }])->with('suscripcion.status')->get(['id','nombre']);

            return response()->json([
                'success' => true,
                'data'=>compact(
                    'clinicas',
                    'suscripciones')
            ]);

        }catch(\Throwable $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
       
    }

    //obtener datos especificos de una clinica
    public function detalle_clinica($clinica_id){
        try{
            $clinica = Clinicas::with(['suscripcion','suscripcion.plan', 'suscripcion.status'])
            ->find($clinica_id);

            $usuarioAdmin=Usuario::with('clinicas')
                ->where('clinica_id',$clinica_id)->first();

            $personalC=Personal::whereHas('usuario',function($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id);
            })->with('usuario','especialidad','usuario.status')->get();

            $servicios=Servicio::with('status')->where('clinica_id',$clinica_id)->get();

            $pacientes=Pacientes::with('status')
                ->where('clinica_id',$clinica_id)->get();

            $pagos=Payment::with('plan')
                ->where('clinica_id',$clinica_id)->get();

            $citas=Citas::whereHas('personal.usuario',function($q) use($clinica_id){
                $q->where('clinica_id',$clinica_id);
            })->with('personal','paciente','status')->orderBy('fecha_cita','asc')->get();

            $especialidades=Especialidad::with('status')->
                where('clinica_id',$clinica_id)->get();

            return response()->json([
                'success'=>true,
                'data'=>compact(
                    'clinica',
                   'personalC',
                    'pacientes',
                    'pagos',
                    'usuarioAdmin',
                    'servicios',
                    'citas',
                    'especialidades'
                )
            ]);

        }catch(\Throwable $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }

    }

    public function index_detalleusuario($usuario_id){

        try{
            $usuarioP=Usuario::with('status')->find($usuario_id);
       
            $especialidad=Especialidad::where('clinica_id',$usuarioP->clinica_id)->get();
            $puesto=Puesto::all();
            $personal=Personal::where('usuario_id',$usuario_id)->first();
            $especialidad_user=Especialidad::where('id',$personal->especialidad_id)->first();
            $puesto_user=Puesto::where('id',$personal->puesto_id)->first();
            // $personal_estado=Usuario::where('id', $usuarioP->status_id)->first();
            $disponibilidad=Disponibilidad::where('personal_id',$personal->id)->get()->keyBy('dia');

            return response()->json([
                'success'=>true,
                'data'=>compact('usuarioP','especialidad',
                'puesto','personal','especialidad_user','puesto_user',
                'disponibilidad')]);

        }catch(\Throwable $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }

    //obtener reportes 
     public function index_reportes(){
        try{

            $ingresos= $this->economiaService->ingresos();
        
            $suscripcionesActivas = Planes::withCount(['suscripcion as total_clinicas' => function ($query) {
                $query->where('status_id', 1);
            }])->get(['id', 'nombre']);

            $suscripcionesActivas->transform(function($item){
                $item->status_id = 1;
                return $item;
            });

            $suscripcionesPorTerminar=Planes::withCount(['suscripcion as total_clinicas' => function ($query) {
                $query->where('status_id', 6);
            }])->get(['id', 'nombre']);

            $suscripcionesPorTerminar->transform(function($item){
                $item->status_id = 6;
                return $item;
            });

             $suscripcionesInactivas=Planes::withCount(['suscripcion as total_clinicas' => function ($query) {
                $query->where('status_id', 2);
            }])->get(['id', 'nombre']);

             $suscripcionesInactivas->transform(function($item){
                $item->status_id = 2;
                return $item;
            });

            return response()->json([ 
                'success'=>true,
                'data'=>compact('suscripcionesActivas',
                'suscripcionesPorTerminar','suscripcionesInactivas','ingresos')]);

        }catch(\Throwable $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    //obtener detalle del reporte
    public function detalle_reporte($plan_id,$status_id){
        try{

            $clinicas=Clinicas::whereHas('suscripcion',function($q) use($plan_id,$status_id){
                $q->where('plan_id',$plan_id);
                $q->where('status_id',$status_id);
            })->get();

            $suscripciones = Planes::withCount(['suscripcion as total_clinicas' => function ($query) use($status_id) {
                $query->where('status_id', $status_id);
            }])->where('id',$plan_id)->get(['id', 'nombre']);

            $status=Status::find($status_id);

            $suscripciones->transform(function($item) use($status){
                $item->status = $status->descripcion;
                return $item;
            });


            return view('clinicas',array_merge(compact('clinicas','suscripciones')));

        }catch(\Throwable $e){
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos',
                'error' => $e->getMessage(),
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

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Models\UsuarioAdmin;
use App\Models\Personal;
use Illuminate\Support\Facades\DB;
use App\Services\RegisterLoginService;

class LoginController extends Controller
{
/**
 * Inicia sesión de un usuario administrador mediante la API.
 *
 * Valida las credenciales recibidas, verifica si el usuario existe y autentica al usuario
 * utilizando el guard por defecto de Laravel. En caso de éxito, devuelve un token de sesión
 * y los datos básicos del usuario autenticado.
 *
 * @param \Illuminate\Http\Request $request
 * @return \Illuminate\Http\JsonResponse
 */
    public function login(Request $request)
    {
        try {
            //Validación de los datos de entrada
            $validated = $request->validate([
                'correo' => 'required|email:rfc,dns',
                'password' => 'required|string',
            ]);

            //Buscar usuario por correo
            $usuario = UsuarioAdmin::where('correo', $validated['correo'])->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Correo no registrado. Comunícate con un administrador.',
                ], 404);
            }

            // 🔐 Intentar autenticar
            if (!Auth::attempt(['correo' => $validated['correo'], 'password' => $validated['password']])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario o contraseña incorrecta.',
                ], 401);
            }

            // 🧑‍💻 Obtener usuario autenticado
            $user = Auth::user();


            // ✅ Respuesta exitosa con datos del usuario y token
            return response()->json([
                'success' => true,
                'message' => 'Inicio de sesión exitoso.',
                'user' => [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'correo' => $user->correo,
                ],
            ], 200);

        } catch (\Exception $e) {
            // 🚫 Manejo de errores inesperados
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al intentar iniciar sesión.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function logout(Request $request){

         // Cierra la sesión del usuario
        Auth::logout();

        //Invalidacion de la sesion actual, se destruye la informacion de la sesion y asegura que el autenticador ya no sea valido
        $request->session()->invalidate();

        // Regenera el token CSRF de la sesión
        $request->session()->regenerateToken();
        
        return redirect(route('login'));
    }
}

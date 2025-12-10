<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UsuarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'correo' => 'email:rfc,dns',
                'password' => 'string',
                'confirm_password'=>'string|same:password',
                
                'nombre'=>'string',
                'apellido_paterno'=>'string',
                'apellido_materno'=>'string',
                'fecha_nacimiento'=>'date',
                'especialidad'=>'integer',
                'cedula_profesional'=>'string',
                'telefono'=>'numeric',
                'puesto'=>'integer',
                'dias' => 'array',
                ['confirm_password.same' => 'Las contraseñas no coinciden'] 
        ];
    }
}

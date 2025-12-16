<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PacienteRequest extends FormRequest
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
            'estado_id'=>'integer',
            'nombre' => 'string',
            'apellido_paterno' => 'string',
            'apellido_materno' => 'string',
            'alias'=>'nullable|string',
            'telefono_paciente'=>'nullable|string',
            'curp'=>'nullable|string',
            'nss'=>'nullable|numeric',
            'direccion.calle'=>'nullable|string',
            'direccion.localidad'=>'nullable|string',
            'direccion.ciudad'=>'nullable|string',

            'observaciones.*' => 'nullable|string|max:255',
            'observaciones' => 'nullable|array', 

            'nombre_familiar'=>'nullable|array',
            'ap_familiar'=>'nullable|array',
            'am_familiar'=>'nullable|array',
            'parentesco'=>'nullable|array',
            'telefono_familiar'=>'nullable|array',
            'direccion_fam.calle'=>'nullable|array',
            'direccion_fam.localidad'=>'nullable|array',
            'direccion_fam.ciudad'=>'nullable|array',
            'familiar_id' => 'nullable|array',

            'peso'=>'nullable|integer',
            'estatura'=>'nullable|integer',
            'imc'=>'nullable|integer',
            'perimetro_cintura'=>'nullable|integer',
            'perimetro_cadera'=>'nullable|integer',
            'perimetro_brazo'=>'nullable|integer',
            'perimetro_cefalico'=>'nullable|integer',

            'origin_view'=>'nullable|string'
        ];
    }
}

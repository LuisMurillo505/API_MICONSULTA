<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HistorialClinicoRequest extends FormRequest
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
            'registro_num' => 'nullable|string|max:255',
            'ocupacion' => 'nullable|string|max:255',
            'cuarto' => 'nullable|string|max:255',
            'sala' => 'nullable|string|max:255',
            'motivo_consulta' => 'nullable|string|max:255',
            'enfermedad_actual' => 'nullable|string|max:255',

            'cardiovasculares' => 'nullable|boolean',
            'pulmonares' => 'nullable|boolean',
            'digestivos' => 'nullable|boolean',
            'diabetes' => 'nullable|boolean',
            'renales' => 'nullable|boolean',
            'quirurgicos' => 'nullable|boolean',
            'alergicos' => 'nullable|boolean',
            'transfusiones' => 'nullable|boolean',

            'medicamentos' => 'nullable|string|max:255',
            'med_especificar' => 'nullable|string|max:255',

            'alcohol' => 'nullable|boolean',
            'tabaquismo' => 'nullable|boolean',
            'drogas' => 'nullable|boolean',
            'inmunizaciones' => 'nullable|boolean',
            'otros_no_patologicos' => 'nullable|string|max:255',

            'padre_vivo' => 'nullable|boolean',
            'padre_enfermedades' => 'nullable|string|max:255',
            'madre_viva' => 'nullable|boolean',
            'madre_enferemedades' => 'nullable|string|max:255',
            'hermanos' => 'nullable|integer',
            'hermanos_enferemedades' => 'nullable|string|max:255',
            'fam_otros' => 'nullable|string|max:255',

            'menarquia' => 'nullable|string|max:255',
            'ritmo' => 'nullable|string|max:255',
            'fum' => 'nullable|string|max:255',
            'ivsa' => 'nullable|string|max:255',
            'g' => 'nullable|string|max:10',
            'p' => 'nullable|string|max:10',
            'a' => 'nullable|string|max:10',
            'c' => 'nullable|string|max:10',
            'usa_anticonceptivos' => 'nullable|boolean',
            'cuales_anticonceptivos' => 'nullable|string|max:255',

            'ta' => 'nullable|string|max:20',
            'fc' => 'nullable|string|max:20',
            'fr' => 'nullable|string|max:20',
            'temp' => 'nullable|string|max:20',

            'cabeza' => 'nullable|string',
            'cuello' => 'nullable|string',
            'torax' => 'nullable|string',
            'abdomen' => 'nullable|string',
            'genitales' => 'nullable|string',
            'extremidades' => 'nullable|string',
            'neurologico' => 'nullable|string',

            'laboratorio' => 'nullable|string',
            'estudio_imagen' => 'nullable|string',
            'otros_examenes' => 'nullable|string',

            'diagnostico' => 'nullable|string',
            'plan_terapeutico' => 'nullable|string',

            'medico_tratante' => 'nullable|string|max:255',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class historial_clinico extends Model
{
    protected $table='historial_clinico'; 

    protected $fillable = [
        'registro_num','ocupacion','cuarto','sala','motivo_consulta','enfermedad_actual',
        'cardiovasculares', 'pulmonares', 'digestivos', 'diabetes', 'renales',
        'quirurgicos', 'alergicos', 'transfusiones','medicamentos','med_especificar',
        'alcohol', 'tabaquismo', 'drogas', 'inmunizaciones', 'otros_no_patologicos',
        'padre_vivo','padre_enfermedades','madre_viva','madre_enfermedades','hermanos',
        'hermanos_enfermedades','fam_otros',
        'menarquia', 'ritmo', 'fum', 'ivsa', 'g', 'p', 'a', 'c',
        'usa_anticonceptivos','cuales_anticonceptivos','ta', 'fc', 'fr', 'temp',
        'cabeza', 'cuello', 'torax', 'abdomen', 'genitales', 'extremidades', 'neurologico',
        'laboratorio','estudios_imagen','otros_examenes', 'diagnostico', 'plan_terapeutico','medico_tratante'
    ];
    
    public function paciente(){
        return $this->BelongsTo(Pacientes::class,'paciente_id');
    }
}

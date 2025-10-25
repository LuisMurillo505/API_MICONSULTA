<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Somatometria_Paciente extends Model
{
    protected $table='somatometria_paciente';

    Protected $fillable=[
        'paciente_id',
        'peso',
        'estatura',
        'IMC',
        'perimetro_cintura',
        'perimetro_cadera',
        'perimetro_brazo',
        'perimetro_cefalico',
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function paciente(){
        return $this->belongsTo(clinicas::class,'paciente_id');
    }
}

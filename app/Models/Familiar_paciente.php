<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Familiar_paciente extends Model
{
    protected $table = 'familiar_paciente';

    protected $primaryKey = 'id';

    // Para los campos que serán asignados masivamente
    protected $fillable = [
        'paciente_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'parentesco',
        'telefono',
    ];

    // Si deseas trabajar con fechas automáticamente
    protected $dates = ['created_at', 'updated_at'];

    // Relación con la tabla 'estado' (estado_id)
   
    public function paciente(){
        return $this->belongsTo(pacientes::class,'paciente_id');
    }  
    
    // public function direccion(){
    //     return $this->belongsTo(direcciones::class,'direccion_id');
    // }
    public function direccion()
    {
        return $this->morphOne(Direcciones::class, 'direccionable');
    }

}

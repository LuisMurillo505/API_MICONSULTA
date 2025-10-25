<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observaciones extends Model
{
     // Nombre de la tabla
     protected $table = 'observaciones';
 
     protected $fillable = [
         'paciente_id',
         'observacion'
     ];
 

     protected $dates = ['created_at', 'updated_at', 'fecha'];
 
     // Relación con la tabla 'paciente' (paciente_id)
     public function paciente()
     {
        return $this->belongsTo(Pacientes::class, 'paciente_id');
     }
 
}

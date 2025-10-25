<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expedientes extends Model
{
     // Nombre de la tabla
     protected $table = 'expediente';
 
     protected $fillable = [
         'paciente_id',
         'personal_id',
         'cita_id',
         'motivo_consulta',
         'objetivo',
         'proceso',
         'resultados',
         'fecha',
     ];
 

     protected $dates = ['created_at', 'updated_at', 'fecha'];
 
     // Relación con la tabla 'paciente' (paciente_id)
     public function paciente()
     {
         return $this->belongsTo(Pacientes::class, 'paciente_id');
     }
 
     // Relación con la tabla 'personal' (personal_id)
     public function personal()
     {
         return $this->belongsTo(Personal::class, 'personal_id');
     }
 
     // Relación con la tabla 'cita' (cita_id)
     public function cita()
     {
         return $this->belongsTo(Citas::class, 'cita_id');
     }
}

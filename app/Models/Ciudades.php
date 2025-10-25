<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ciudades extends Model
{
    protected $table = 'ciudades';

     // Para los campos que serán asignados masivamente
     protected $fillable = [
        'nombre'
    ];

    protected $dates = ['created_at', 'updated_at', 'fecha_cita'];

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funciones extends Model
{
     protected $table='funciones';
     protected $fillable = ['nombre', 'descripcion'];

     public function funciones_planes()
    {
        return $this->hasOne(Funciones_planes::class, 'funcion_id');
    }
}

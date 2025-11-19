<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Funciones_planes extends Model
{
     protected $table='funciones_planes';
     protected $fillable = ['plan_id', 'funcion_id', 'cantidad'];

    public function plan()
    {
        return $this->belongsTo(Planes::class, 'plan_id');
    }

    public function funcion(){
        return $this->belongsTo(Funciones::class,'funcion_id');
    }
}

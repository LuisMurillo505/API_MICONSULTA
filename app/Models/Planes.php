<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Planes extends Model
{
     protected $table='planes';
     protected $fillable = ['id','nombre', 'duracion_dias', 'dias_espera','precio', 'stripe_price_id'];

    public function funciones_planes()
    {
        return $this->hasOne(Funciones_planes::class,'plan_id');
    }


      public function suscripcion()
    {
        return $this->hasOne(Suscripcion::class, 'plan_id');
    }
}

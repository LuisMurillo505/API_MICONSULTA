<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guia_Configuracion extends Model
{
    protected $table='guia_configuracion';

    Protected $fillable=[
        'clinica_id',
        'paso',
        'progreso'
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function clinicas(){
        return $this->belongsTo(clinicas::class,'clinica_id');
    }
}

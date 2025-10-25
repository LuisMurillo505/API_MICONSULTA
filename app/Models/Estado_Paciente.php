<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estado_Paciente extends Model
{
    protected $table='ESTADO_PACIENTE';

    protected $fillable=[
        'estado'
    ];

    protected $dates = ['created_at', 'updated_at'];

}

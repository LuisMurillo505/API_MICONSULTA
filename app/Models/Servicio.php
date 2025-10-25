<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    protected $table='SERVICIO';

    protected $fillable=[
        'descripcion',
        'estado'
    ];

    
    protected $dates = ['created_at', 'updated_at'];
}

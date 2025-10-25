<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permisos extends Model
{
    protected $table='permisos';

    Protected $fillable=[
        'descripcion'
        
    ];

    protected $dates = ['created_at', 'updated_at'];
}

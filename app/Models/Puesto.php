<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Puesto extends Model
{

    protected $table = 'puesto';
    protected $fillable=[
        'descripcion',
        'estado'
    ];

    protected $dates = ['created_at', 'updated_at'];
}

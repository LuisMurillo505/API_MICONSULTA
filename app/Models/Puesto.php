<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Puesto extends Model
{

    protected $table = 'PUESTO';
    protected $fillable=[
        'descripcion'
    ];

    protected $dates = ['created_at', 'updated_at'];
}

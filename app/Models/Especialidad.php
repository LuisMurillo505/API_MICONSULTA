<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    
    protected $table = 'ESPECIALIDAD';
    protected $fillable = [
        'descripcion',
    ];

    protected $dates = ['created_at', 'updated_at'];

}

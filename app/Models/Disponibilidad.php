<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Disponibilidad extends Model
{
    protected $table = 'disponibilidad_medico';
    protected $fillable = [
        'dia',
        'hora_inicio',
        'hora_fin',
        'personal_id',
    ];

    protected $dates = ['created_at', 'updated_at'];

    public function personal()
    {
        return $this->belongsTo(Personal::class,'personal_id');
    }
    
}




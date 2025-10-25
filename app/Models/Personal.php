<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
     protected $table = 'PERSONAL';
    
     protected $fillable = [
        'usuario_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'especialidad_id',
        'cedula_profesional',
        'telefono',
        'correo',
        'foto',
    ];
    protected $dates = ['created_at', 'updated_at', 'fecha_nacimiento'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    
}

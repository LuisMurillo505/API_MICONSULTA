<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pacientes extends Model
{
    protected $table = 'PACIENTE';

    // Si deseas especificar la clave primaria (esto es opcional si es 'id')
    protected $primaryKey = 'id';

    // Para los campos que serán asignados masivamente
    protected $fillable = [
        'estado_id',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'foto',
    ];

    // Si deseas trabajar con fechas automáticamente
    protected $dates = ['created_at', 'updated_at', 'fecha_nacimiento'];

    // Relación con la tabla 'estado' (estado_id)
    public function estado()
    {
        return $this->belongsTo(Estado_Paciente::class, 'estado_id');
    }
}

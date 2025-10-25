<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Citas extends Model
{
    protected $table = 'CITA';

     // Para los campos que serán asignados masivamente
     protected $fillable = [
        'personal_id',
        'paciente_id',
        'servicio_id',
        'fecha_cita',
        'hora',
        'estado',
    ];

    // Si deseas trabajar con fechas automáticamente
    protected $dates = ['created_at', 'updated_at', 'fecha_cita'];

    // Relación con la tabla 'personal' (personal_id)
    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    // Relación con la tabla 'paciente' (paciente_id)
    public function paciente()
    {
        return $this->belongsTo(Pacientes::class, 'paciente_id');
    }

    // Relación con la tabla 'servicios' (servicio_id)
    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'servicio_id');
    }
}

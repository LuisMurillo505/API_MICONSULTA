<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivosPaciente extends Model
{
    protected $table = 'archivos_pacientes';

    // Si deseas especificar la clave primaria (esto es opcional si es 'id')
    protected $primaryKey = 'id';

    // Para los campos que serán asignados masivamente
    protected $fillable = [
        'status_id',
        'paciente_id',
        'nombre',
        'ruta',
        'tipo',
        'tamano'
    ];

    public function paciente()
    {
        return $this->belongsTo(Pacientes::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

}
